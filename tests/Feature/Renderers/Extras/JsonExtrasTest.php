<?php

namespace Tests\Feature\Renderers\Extras;

use App\Renderers\Extras\Json;
use Tests\TestCase;

/**
 * The Json extras renderer dumps whole reference tables - languages, Strong's definitions,
 * book lists and shortcuts - to .json files for download.
 *
 * Nothing in the suite exercised it before. These tests read the installed reference tables
 * and write into a throwaway directory, so no content data is altered and nothing lands in
 * bibles/rendered.
 */
class JsonExtrasTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/bss-extras-json-' . uniqid() . '/';
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

    /** A Json renderer that writes into the temp directory instead of bibles/rendered. */
    private function makeRenderer(): Json
    {
        $dir = $this->tempDir;

        return new class ($dir) extends Json {
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

    /**
     * @return array<int, \stdClass>
     */
    private function decode(string $path): array
    {
        $this->assertFileExists($path);

        $decoded = json_decode(file_get_contents($path));

        $this->assertIsArray($decoded, 'the dump should be a JSON array');

        return $decoded;
    }

    public function testLanguagesAreDumpedAsJson(): void
    {
        $path = $this->makeRenderer()->callLanguages();

        $this->assertSame($this->tempDir . 'languages.json', $path);

        $rows = $this->decode($path);

        $this->assertNotEmpty($rows);
        $this->assertObjectHasProperty('code', $rows[0]);
        $this->assertObjectHasProperty('name', $rows[0]);
    }

    /**
     * The dump is a download artefact, so the internal bookkeeping columns are stripped.
     */
    public function testTimestampColumnsAreStrippedFromTheDump(): void
    {
        $rows = $this->decode($this->makeRenderer()->callLanguages());

        $this->assertObjectNotHasProperty('created_at', $rows[0]);
        $this->assertObjectNotHasProperty('updated_at', $rows[0]);
    }

    public function testStrongsDefinitionsAreDumped(): void
    {
        $path = $this->makeRenderer()->callStrongs();

        $this->assertSame($this->tempDir . 'strongs_definitions.json', $path);
        $this->assertNotEmpty($this->decode($path));
    }

    public function testBookListIsDumpedPerLanguage(): void
    {
        $path = $this->makeRenderer()->callBookList('en');

        $this->assertSame($this->tempDir . 'books_en.json', $path);

        $rows = $this->decode($path);

        $this->assertCount(66, $rows, 'the English book list should hold all 66 books');
        $this->assertObjectHasProperty('name', $rows[0]);
    }

    public function testShortcutsAreDumpedPerLanguage(): void
    {
        $path = $this->makeRenderer()->callShortcuts('en');

        $this->assertSame($this->tempDir . 'shortcuts_en.json', $path);
        $this->assertNotEmpty($this->decode($path));
    }

    /**
     * getFileList() is what the packaging step zips, so every helper's output has to be
     * reachable through the manifest once a full render has run.
     */
    public function testFileNamesAreDerivedFromTheLanguageCode(): void
    {
        $renderer = $this->makeRenderer();

        $this->assertStringEndsWith('books_en.json', $renderer->callBookList('en'));
        $this->assertStringEndsWith('shortcuts_en.json', $renderer->callShortcuts('en'));
    }
}
