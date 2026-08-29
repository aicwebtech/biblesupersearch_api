<?php

namespace Tests\Feature\Renderers\Extras;

use App\Renderers\Extras\MySQL;
use Tests\TestCase;

/**
 * The MySQL extras renderer emits importable .sql dumps of the reference tables.
 *
 * Nothing in the suite exercised it before. These tests read the installed reference tables
 * and write into a throwaway directory, so no content data is altered and nothing lands in
 * bibles/rendered.
 */
class MysqlExtrasTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/bss-extras-mysql-' . uniqid() . '/';
        mkdir($this->tempDir, 0775, true);
    }

    public function tearDown(): void
    {
        foreach (glob($this->tempDir . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    private function makeRenderer(): MySQL
    {
        $dir = $this->tempDir;

        return new class ($dir) extends MySQL {
            public function __construct(private string $dir) {}

            public function getRenderFileDir($create_dir = true)
            {
                return $this->dir;
            }

            public function callLanguages()
            {
                return $this->_renderLanguagesHelper();
            }

            public function callBookList(string $lang)
            {
                return $this->_renderBibleBookListSingle($lang);
            }

            public function callShortcuts(string $lang)
            {
                return $this->_renderBibleShortcutsSingle($lang);
            }
        };
    }

    /**
     * The generic dump path issues SHOW CREATE TABLE, which only MySQL understands. Tests that
     * reach it are skipped on any other driver (CI runs the suite on SQLite).
     */
    private function requireMysqlDriver(): void
    {
        if(\DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('The generic MySQL dump requires the mysql driver');
        }
    }

    public function testLanguagesDumpIsWrittenAsImportableSql(): void
    {
        $this->requireMysqlDriver();

        $path = $this->makeRenderer()->callLanguages();

        $this->assertSame($this->tempDir . 'languages.sql', $path);
        $this->assertFileExists($path);

        $sql = file_get_contents($path);

        $this->assertStringContainsString('DROP TABLE IF EXISTS `bible_languages`;', $sql);
        $this->assertStringContainsString('CREATE TABLE `bible_languages`', $sql);
        $this->assertStringContainsString('INSERT INTO `bible_languages`', $sql);
    }

    /**
     * The dump is meant to be imported into a fresh database, so a hard-coded
     * AUTO_INCREMENT counter from this installation must not travel with it.
     */
    public function testAutoIncrementCounterIsStrippedFromTheCreateStatement(): void
    {
        $this->requireMysqlDriver();

        $sql = file_get_contents($this->makeRenderer()->callLanguages());

        $this->assertDoesNotMatchRegularExpression('/AUTO_INCREMENT=[0-9]+/', $sql);
    }

    /**
     * The installation's table prefix is local to this deployment; the dump has to carry the
     * portable bible_ name instead.
     */
    public function testLocalTablePrefixDoesNotLeakIntoTheDump(): void
    {
        $this->requireMysqlDriver();

        $prefix = env('DB_PREFIX');

        if(empty($prefix)) {
            $this->markTestSkipped('No table prefix configured, so there is no prefix that could leak');
        }

        $sql = file_get_contents($this->makeRenderer()->callLanguages());

        $this->assertStringNotContainsString($prefix . 'languages', $sql);
    }

    /**
     * The installed reference tables all carry NULL timestamps already, so the nulling can only
     * be observed against a throwaway table that has real ones. The dump is produced through the
     * private generic helper, which the two public helpers hard-code their table names into.
     */
    public function testTimestampsAreNulledRatherThanCarriedIntoTheDump(): void
    {
        $this->requireMysqlDriver();

        $stamp = '2019-04-01 12:34:56';
        $table = env('DB_PREFIX') . 'extras_dump_fixture';
        $path  = $this->tempDir . 'fixture.sql';

        \DB::statement("DROP TABLE IF EXISTS `{$table}`");
        \DB::statement("CREATE TABLE `{$table}` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, `created_at` timestamp NULL DEFAULT NULL, `updated_at` timestamp NULL DEFAULT NULL, PRIMARY KEY (`id`))");

        try {
            \DB::table('extras_dump_fixture')->insert([
                'name'       => 'fixture',
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ]);

            $dump = new \ReflectionMethod(MySQL::class, '_dumpMysqlGeneric');
            $dump->setAccessible(true);
            $dump->invoke($this->makeRenderer(), 'extras_dump_fixture', 'bible_extras_dump_fixture', $path);

            $sql = file_get_contents($path);

            $this->assertStringContainsString('INSERT INTO `bible_extras_dump_fixture`', $sql);
            $this->assertStringNotContainsString($stamp, $sql, 'this installation\'s timestamps must not travel with the dump');
            $this->assertStringContainsString('NULL, NULL);', $sql);
        }
        finally {
            \DB::statement("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    public function testBookListDumpCarriesItsOwnSchemaAndRows(): void
    {
        $path = $this->makeRenderer()->callBookList('en');

        $this->assertSame($this->tempDir . 'bible_books_en.sql', $path);
        $this->assertFileExists($path);

        $sql = file_get_contents($path);

        $this->assertStringContainsString('DROP TABLE IF EXISTS `bible_books_en`;', $sql);
        $this->assertStringContainsString('CREATE TABLE `bible_books_en`', $sql);
        $this->assertSame(66, substr_count($sql, 'INSERT INTO `bible_books_en`'), 'one insert per book');
        $this->assertStringContainsString("'Genesis'", $sql);
    }

    /**
     * A language with no book class must bail out rather than emit an empty dump that would
     * drop the target table on import.
     */
    public function testBookListReturnsNothingForALanguageWithNoBookClass(): void
    {
        $this->assertNull($this->makeRenderer()->callBookList('nonexistent_language'));
        $this->assertSame([], glob($this->tempDir . '*') ?: []);
    }

    public function testShortcutsDumpIsBuiltFromTheCheckedInDump(): void
    {
        $path = $this->makeRenderer()->callShortcuts('en');

        $this->assertSame($this->tempDir . 'shortcuts_en.sql', $path);
        $this->assertFileExists($path);

        $sql = file_get_contents($path);

        $this->assertStringContainsString('DROP TABLE IF EXISTS `bible_shortcuts_en`;', $sql);
        $this->assertStringContainsString('CREATE TABLE `bible_shortcuts_en`', $sql);
    }
}
