<?php

namespace Tests\Feature\Renderers\Extras;

use App\Renderers\Extras\MySQL;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * Table names that have to be interpolated into raw SQL - SHOW CREATE TABLE and the
     * DROP/INSERT statements, none of which the query builder can wrap - must be bare
     * identifiers. Anything carrying a quote, a space or a comment marker is rejected before
     * it reaches the database, and no half-written dump is left behind.
     *
     * @return array<string, array{string, string}>
     */
    public static function unsafeTableIdentifierProvider(): array
    {
        return [
            'quote escape in source table'  => ['languages` WHERE 1=1 -- ', 'bible_languages'],
            'statement break in source'     => ['languages; DROP TABLE users', 'bible_languages'],
            'quote escape in backup table'  => ['languages', 'bible_languages`; DROP TABLE users; -- '],
            'space in backup table'         => ['languages', 'bible languages'],
        ];
    }

    #[DataProvider('unsafeTableIdentifierProvider')]
    public function testGenericDumpRejectsAnUnsafeTableIdentifier(string $db_table, string $bk_table): void
    {
        $path = $this->tempDir . 'unsafe.sql';
        $dump = new \ReflectionMethod(MySQL::class, '_dumpMysqlGeneric');

        try {
            $dump->invoke($this->makeRenderer(), $db_table, $bk_table, $path);
            $this->fail('an unsafe table identifier should not have reached the database');
        }
        catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Unsafe table name', $e->getMessage());
        }

        $this->assertFileDoesNotExist($path);
    }

    /**
     * The connection's table prefix is the deployment's own configuration rather than a name this
     * renderer assembles, and MySQL accepts a backticked prefix carrying a hyphen or other
     * punctuation. Such a prefix has to be escaped into the raw statements; rejecting it would
     * abort the whole extras render on installations that use one.
     */
    public function testGenericDumpAcceptsATablePrefixCarryingPunctuation(): void
    {
        $this->requireMysqlDriver();

        $prefix   = 'bss-fixture-';
        $table    = $prefix . 'extras_dump_fixture';
        $path     = $this->tempDir . 'prefixed.sql';
        $original = \DB::getTablePrefix();

        \DB::statement("DROP TABLE IF EXISTS `{$table}`");
        \DB::statement("CREATE TABLE `{$table}` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, PRIMARY KEY (`id`))");

        try {
            \DB::connection()->setTablePrefix($prefix);
            \DB::table('extras_dump_fixture')->insert(['name' => 'fixture']);

            $dump = new \ReflectionMethod(MySQL::class, '_dumpMysqlGeneric');
            $dump->invoke($this->makeRenderer(), 'extras_dump_fixture', 'bible_extras_dump_fixture', $path);

            $sql = file_get_contents($path);

            $this->assertStringContainsString('CREATE TABLE `bible_extras_dump_fixture`', $sql);
            $this->assertStringContainsString('INSERT INTO `bible_extras_dump_fixture`', $sql);
            $this->assertStringNotContainsString($prefix, $sql, 'the local prefix must not travel with the dump');
        }
        finally {
            \DB::connection()->setTablePrefix($original);
            \DB::statement("DROP TABLE IF EXISTS `{$table}`");
        }
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nonStringTableIdentifierProvider(): array
    {
        return [
            'array'  => [['languages']],
            'object' => [new \stdClass],
        ];
    }

    /**
     * The guard's own error contract: a non-string argument has to raise the documented
     * InvalidArgumentException rather than an Error or a warning from concatenating it into the
     * message.
     */
    #[DataProvider('nonStringTableIdentifierProvider')]
    public function testGenericDumpRejectsANonStringTableIdentifier(mixed $db_table): void
    {
        $path = $this->tempDir . 'unsafe.sql';
        $dump = new \ReflectionMethod(MySQL::class, '_dumpMysqlGeneric');

        try {
            $dump->invoke($this->makeRenderer(), $db_table, 'bible_languages', $path);
            $this->fail('a non-string table identifier should not have reached the database');
        }
        catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Unsafe table name', $e->getMessage());
        }

        $this->assertFileDoesNotExist($path);
    }

    /**
     * A table that exists but holds no rows still has a schema worth dumping. Reading the column
     * list off the first row meant an empty table raised a TypeError instead.
     */
    public function testGenericDumpOfAnEmptyTableCarriesItsSchemaWithoutInserts(): void
    {
        $this->requireMysqlDriver();

        $table = \DB::getTablePrefix() . 'extras_dump_fixture';
        $path  = $this->tempDir . 'empty.sql';

        \DB::statement("DROP TABLE IF EXISTS `{$table}`");
        \DB::statement("CREATE TABLE `{$table}` (`id` int(10) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, PRIMARY KEY (`id`))");

        try {
            $dump = new \ReflectionMethod(MySQL::class, '_dumpMysqlGeneric');
            $dump->invoke($this->makeRenderer(), 'extras_dump_fixture', 'bible_extras_dump_fixture', $path);

            $sql = file_get_contents($path);

            $this->assertStringContainsString('DROP TABLE IF EXISTS `bible_extras_dump_fixture`;', $sql);
            $this->assertStringContainsString('CREATE TABLE `bible_extras_dump_fixture`', $sql);
            $this->assertStringNotContainsString('INSERT INTO', $sql, 'there is nothing to insert');
        }
        finally {
            \DB::statement("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}
