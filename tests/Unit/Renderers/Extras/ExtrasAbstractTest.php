<?php

namespace Tests\Unit\Renderers\Extras;

use PHPUnit\Framework\TestCase;
use App\Renderers\Extras\ExtrasAbstract;

/**
 * ExtrasAbstract orchestrates the "extras" render - book lists, languages, shortcuts and
 * Strong's definitions - and keeps the manifest of what it produced.
 *
 * Nothing in the suite exercised this class before. The subclass below stubs out the four
 * hooks that reach the database or the config, so the orchestration, the file bookkeeping
 * and the readme writer can be asserted with no application booted.
 */
class ExtrasAbstractTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bss-extras-' . uniqid() . '/';
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    /**
     * A concrete renderer whose hooks return fixed paths instead of writing real dumps.
     * _renderBibleBookLists and _renderBibleShortcuts are replaced wholesale because the
     * originals call Language::haveBookSupport() and config().
     */
    private function makeRenderer(): ExtrasAbstract
    {
        $dir = $this->tempDir;

        return new class ($dir) extends ExtrasAbstract {
            public function __construct(private string $dir) {}

            public function getRenderFileDir($create_dir = true)
            {
                return $this->dir;
            }

            protected function _renderBibleBookLists()
            {
                $this->_pushFileInfo('books', $this->dir . 'books_en.sql', 'English');
                $this->_pushFileInfo('books', $this->dir . 'books_de.sql', 'German');
            }

            protected function _renderBibleShortcuts()
            {
                $this->_pushFileInfo('misc', $this->dir . 'shortcuts_en.sql', 'Bible Search Shortcuts');
            }

            protected function _renderStrongsDefinitionsHelper()
            {
                return $this->dir . 'strongs.sql';
            }

            protected function _renderLanguagesHelper()
            {
                return $this->dir . 'languages.sql';
            }

            public function callPushFileInfo($list, $filepath, $filedesc)
            {
                return $this->_pushFileInfo($list, $filepath, $filedesc);
            }

            public function callGetDbDumpDir(): string
            {
                return $this->_getDBDumpDir();
            }
        };
    }

    public function testRenderReportsSuccessWhenNoErrorsAccumulate(): void
    {
        $this->assertTrue($this->makeRenderer()->render());
    }

    public function testRenderCollectsEveryProducedFile(): void
    {
        $renderer = $this->makeRenderer();
        $renderer->render();

        $files = array_map('basename', $renderer->getFileList());

        $this->assertContains('books_en.sql', $files);
        $this->assertContains('books_de.sql', $files);
        $this->assertContains('languages.sql', $files);
        $this->assertContains('shortcuts_en.sql', $files);
        $this->assertContains('strongs.sql', $files);
        $this->assertContains('readme.txt', $files);
    }

    public function testRenderGroupsBookListsSeparatelyFromMiscellany(): void
    {
        $renderer = $this->makeRenderer();
        $renderer->render();

        $info = $renderer->getFileInfo();

        $this->assertCount(2, $info['books']['items']);
        $this->assertSame('English', $info['books']['items'][0]['desc']);
        $this->assertContains('Languages', array_column($info['misc']['items'], 'desc'));
        $this->assertContains("Strong's Definitions", array_column($info['misc']['items'], 'desc'));
    }

    public function testFileInfoStartsEmptyButKeepsItsGroups(): void
    {
        $info = $this->makeRenderer()->getFileInfo();

        $this->assertSame([], $info['books']['items']);
        $this->assertSame([], $info['misc']['items']);
        $this->assertSame('Bible Book Lists', $info['books']['desc']);
    }

    public function testPushFileInfoRecordsPathNameAndDescription(): void
    {
        $renderer = $this->makeRenderer();
        $renderer->callPushFileInfo('misc', '/tmp/somewhere/languages.sql', 'Languages');

        $item = $renderer->getFileInfo()['misc']['items'][0];

        $this->assertSame('Languages', $item['desc']);
        $this->assertSame('/tmp/somewhere/languages.sql', $item['path']);
        $this->assertSame('languages.sql', $item['file']);
    }

    /**
     * An unknown group would otherwise create a manifest section the readme never renders.
     */
    public function testPushFileInfoRejectsAnUnknownGroup(): void
    {
        $renderer = $this->makeRenderer();

        $this->assertFalse($renderer->callPushFileInfo('nonsense', '/tmp/a.sql', 'A'));
        $this->assertArrayNotHasKey('nonsense', $renderer->getFileInfo());
    }

    public function testPushFileInfoRejectsAnEmptyPath(): void
    {
        $renderer = $this->makeRenderer();

        $this->assertFalse($renderer->callPushFileInfo('misc', '', 'A'));
        $this->assertSame([], $renderer->getFileInfo()['misc']['items']);
    }

    public function testReadmeListsEveryRenderedFile(): void
    {
        $renderer = $this->makeRenderer();
        $renderer->render();

        $readme = file_get_contents($this->tempDir . 'readme.txt');

        $this->assertStringStartsWith('Bible SuperSearch Extras', $readme);
        $this->assertStringContainsString('Bible Book Lists', $readme);
        $this->assertStringContainsString('books_en.sql', $readme);
        $this->assertStringContainsString('English', $readme);
        $this->assertStringContainsString('Miscellaneous', $readme);
        $this->assertStringContainsString('strongs.sql', $readme);
    }

    public function testDatabaseDumpDirectoryResolvesInsideTheProject(): void
    {
        $this->assertStringEndsWith('/database/dumps/', $this->makeRenderer()->callGetDbDumpDir());
    }

    public function testRenderBasePathResolvesToTheRenderedExtrasDirectory(): void
    {
        $this->assertStringEndsWith('/bibles/rendered/extras', ExtrasAbstract::getRenderBasePath());
    }

    /**
     * The base class's hooks are guards - a subclass that forgets one must fail loudly
     * rather than silently render nothing.
     *
     * Asserted as Throwable rather than a specific class: these sites throw
     * \StandardException, which is not defined anywhere in the application or its
     * dependencies, so today they raise an Error ("Class not found") instead. Reported
     * rather than fixed - this ticket does not change production code.
     */
    public function testUnimplementedHooksThrow(): void
    {
        $renderer = new class extends ExtrasAbstract {
            public function callStrongsHelper()
            {
                return $this->_renderStrongsDefinitionsHelper();
            }
        };

        $this->expectException(\Throwable::class);

        $renderer->callStrongsHelper();
    }
}
