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

    public function testLanguagesDumpIsWrittenAsImportableSql(): void
    {
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
        $sql = file_get_contents($this->makeRenderer()->callLanguages());

        $this->assertDoesNotMatchRegularExpression('/AUTO_INCREMENT=[0-9]+/', $sql);
    }

    /**
     * The installation's table prefix is local to this deployment; the dump has to carry the
     * portable bible_ name instead.
     */
    public function testLocalTablePrefixDoesNotLeakIntoTheDump(): void
    {
        $prefix = env('DB_PREFIX');

        $this->assertNotEmpty($prefix, 'this test is only meaningful with a table prefix configured');

        $sql = file_get_contents($this->makeRenderer()->callLanguages());

        $this->assertStringNotContainsString($prefix . 'languages', $sql);
    }

    public function testTimestampsAreNulledRatherThanCarriedIntoTheDump(): void
    {
        $sql = file_get_contents($this->makeRenderer()->callLanguages());

        $this->assertStringContainsString('INSERT INTO `bible_languages`', $sql);
        $this->assertStringContainsString('NULL', $sql);
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
