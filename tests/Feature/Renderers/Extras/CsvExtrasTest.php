<?php

namespace Tests\Feature\Renderers\Extras;

use App\Renderers\Extras\Csv;
use Tests\TestCase;

/**
 * The Csv extras renderer mostly copies the checked-in dumps in database/dumps into the
 * render directory, and generates the shortcuts file from the database.
 *
 * Nothing in the suite exercised it before. Output goes to a throwaway directory; the source
 * dumps are only read.
 */
class CsvExtrasTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/bss-extras-csv-' . uniqid() . '/';
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

    private function makeRenderer(): Csv
    {
        $dir = $this->tempDir;

        return new class ($dir) extends Csv {
            public function __construct(private string $dir) {}

            public function getRenderFileDir($create_dir = true)
            {
                return $this->dir;
            }

            public function callLanguages()
            {
                return $this->_renderLanguagesHelper();
            }

            public function callStrongs()
            {
                return $this->_renderStrongsDefinitionsHelper();
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

    public function testLanguagesCsvIsCopiedIntoTheRenderDirectory(): void
    {
        $path = $this->makeRenderer()->callLanguages();

        $this->assertSame($this->tempDir . 'languages.csv', $path);
        $this->assertFileExists($path);
        $this->assertFileEquals(base_path('database/dumps/languages.csv'), $path);
    }

    public function testStrongsCsvIsCopiedIntoTheRenderDirectory(): void
    {
        $path = $this->makeRenderer()->callStrongs();

        $this->assertSame($this->tempDir . 'strongs_definitions.csv', $path);
        $this->assertFileEquals(base_path('database/dumps/strongs_definitions.csv'), $path);
    }

    /**
     * The book lists are stored per language under bible_books/ but published under a
     * books_{lang} name, so the copy renames as well as moves.
     */
    public function testBookListIsCopiedAndRenamed(): void
    {
        $path = $this->makeRenderer()->callBookList('en');

        $this->assertSame($this->tempDir . 'books_en.csv', $path);
        $this->assertFileEquals(base_path('database/dumps/bible_books/en.csv'), $path);
    }

    /**
     * A language with no checked-in dump must fail with a catchable exception naming the
     * missing file, and must not emit a PHP warning on the way there.
     *
     * _copyDbDumpFileToRendered() called copy() unguarded until BSS-285, so a missing source
     * warned first and then threw \StandardException - a class that is defined nowhere, making
     * the failure an uncatchable Error rather than the intended exception.
     */
    public function testCopyingAMissingSourceDumpThrows(): void
    {
        $renderer = $this->makeRenderer();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('source file does not exist');

        $renderer->callBookList('zz');
    }

    /**
     * The exception must be catchable as an ordinary Exception: RenderManager::renderExtras()
     * wraps the render in catch(\Exception), which an Error would escape.
     */
    public function testAMissingSourceDumpFailureIsCatchableAsAnException(): void
    {
        $caught = null;

        try {
            $this->makeRenderer()->callBookList('zz');
        }
        catch (\Exception $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(\Exception::class, $caught);
        $this->assertStringContainsString('zz.csv', $caught->getMessage());
        $this->assertFileDoesNotExist($this->tempDir . 'books_zz.csv');
    }

    public function testShortcutsAreGeneratedFromTheDatabase(): void
    {
        $path = $this->makeRenderer()->callShortcuts('en');

        $this->assertSame($this->tempDir . 'shortcuts_en.csv', $path);
        $this->assertFileExists($path);

        // Every argument is passed explicitly: str_getcsv's $escape default is changing, and
        // omitting it is deprecated from PHP 8.4. The escape character matches the one the
        // renderer writes with (Csv::$escape).
        $rows = array_map(
            static fn (string $line): array => str_getcsv($line, ',', '"', '\\'),
            file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
        );

        $this->assertNotEmpty($rows);
        $this->assertContains('name', $rows[0], 'the first row should be the column header');
        $this->assertNotContains('created_at', $rows[0], 'bookkeeping columns are excluded');
        $this->assertNotContains('updated_at', $rows[0]);
        $this->assertGreaterThan(1, count($rows), 'the export should carry at least one shortcut');
    }
}
