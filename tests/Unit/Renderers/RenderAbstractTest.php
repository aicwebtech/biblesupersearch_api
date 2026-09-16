<?php

namespace Tests\Unit\Renderers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Renderers\RenderAbstract;

class RenderAbstractTest extends TestCase
{
    /** Returns a concrete anonymous subclass that skips the DB-dependent constructor. */
    private function makeRenderer(): RenderAbstract
    {
        return new class extends RenderAbstract {
            protected $file_extension = 'txt';

            public function __construct()
            {
                // intentionally skip parent constructor (which needs Bible::findByModule)
            }

            protected function _renderSingleVerse($verse) {}

            public function callHtmlToPlainText(string $html, ?string $sep = null): string
            {
                return $this->_htmlToPlainText($html, $sep);
            }

            public static function callRemoveStaleFile($file_path): void
            {
                static::removeStaleFile($file_path);
            }
        };
    }

    // -----------------------------------------------------------------------
    // removeStaleFile (App\Traits\RemovesStaleFiles)
    //
    // Every renderer removes whatever sits at the render path before writing a
    // new artifact there. The guard used to be is_file(), which reports on a
    // symlink's *target*: a dangling link is therefore invisible to it, survived
    // the guard, and the write that followed resolved through the link.
    // -----------------------------------------------------------------------

    /** @var string|null */
    private $scratch_dir = null;

    private function scratchDir(): string
    {
        if($this->scratch_dir === null) {
            $this->scratch_dir = sys_get_temp_dir() . '/bss_render_stale_' . bin2hex(random_bytes(6));
            mkdir($this->scratch_dir);
        }

        return $this->scratch_dir;
    }

    protected function tearDown(): void
    {
        if($this->scratch_dir !== null) {
            foreach(glob($this->scratch_dir . '/*') ?: [] as $path) {
                is_dir($path) && !is_link($path) ? rmdir($path) : unlink($path);
            }

            // glob() skips dangling links, so sweep them separately.
            foreach(scandir($this->scratch_dir) as $entry) {
                if($entry !== '.' && $entry !== '..') {
                    unlink($this->scratch_dir . '/' . $entry);
                }
            }

            rmdir($this->scratch_dir);
            $this->scratch_dir = null;
        }

        parent::tearDown();
    }

    /**
     * The case is_file() missed: fopen(..., 'w') through a surviving dangling
     * link creates or truncates the link target instead of the render file.
     */
    public function testDanglingSymlinkIsRemovedNotFollowed(): void
    {
        $dir = $this->scratchDir();
        $target = $dir . '/outside_target';
        $link = $dir . '/render.txt';

        symlink($target, $link);

        $this->assertFalse(is_file($link), 'Precondition: is_file() cannot see a dangling link');
        $this->assertTrue(is_link($link), 'Precondition: is_link() can');

        $this->makeRenderer()::callRemoveStaleFile($link);

        $this->assertFalse(is_link($link), 'The link must be gone');

        fclose(fopen($link, 'w'));

        $this->assertFileDoesNotExist($target, 'The write must not have gone through the link');
        $this->assertTrue(is_file($link) && !is_link($link), 'A real file must exist at the render path');
    }

    /**
     * A link to a *directory* is also invisible to is_file().
     */
    public function testSymlinkToDirectoryIsRemoved(): void
    {
        $dir = $this->scratchDir();
        $target = $dir . '/a_directory';
        $link = $dir . '/render.txt';

        mkdir($target);
        symlink($target, $link);

        $this->assertFalse(is_file($link), 'Precondition: is_file() is false for a link to a directory');

        $this->makeRenderer()::callRemoveStaleFile($link);

        $this->assertFalse(is_link($link), 'The link must be gone');
        $this->assertDirectoryExists($target, 'Only the link is removed, never its target');
    }

    /**
     * A live link is unlinked rather than followed, so the file it points at is
     * left alone.
     */
    public function testLiveSymlinkIsRemovedButItsTargetSurvives(): void
    {
        $dir = $this->scratchDir();
        $target = $dir . '/real_file';
        $link = $dir . '/render.txt';

        file_put_contents($target, 'keep me');
        symlink($target, $link);

        $this->makeRenderer()::callRemoveStaleFile($link);

        $this->assertFalse(is_link($link), 'The link must be gone');
        $this->assertSame('keep me', file_get_contents($target), 'The target must be untouched');
    }

    public function testOrdinaryStaleFileIsRemoved(): void
    {
        $file = $this->scratchDir() . '/render.txt';
        file_put_contents($file, 'stale');

        $this->makeRenderer()::callRemoveStaleFile($file);

        $this->assertFileDoesNotExist($file);
    }

    public function testMissingPathIsANoOp(): void
    {
        $file = $this->scratchDir() . '/never_existed.txt';

        $this->makeRenderer()::callRemoveStaleFile($file);

        $this->assertFileDoesNotExist($file);
    }

    // -----------------------------------------------------------------------
    // renderStrongs
    // -----------------------------------------------------------------------

    #[DataProvider('renderStrongsDataProvider')]
    public function testRenderStrongs(string $lang, bool $expected): void
    {
        $this->assertSame($expected, RenderAbstract::renderStrongs($lang));
    }

    public static function renderStrongsDataProvider(): array
    {
        return [
            'English (en)'   => ['en', true],
            'Spanish (es)'   => ['es', true],
            'Chinese (zh)'   => ['zh', true],
            'French (fr)'    => ['fr', false],
            'German (de)'    => ['de', false],
            'Russian (ru)'   => ['ru', false],
            'Latvian (lv)'   => ['lv', false],
            'Empty string'   => ['',   false],
        ];
    }

    // -----------------------------------------------------------------------
    // getRenderBasePath
    // -----------------------------------------------------------------------

    public function testGetRenderBasePathReturnsStringWithRenderedDir(): void
    {
        $path = RenderAbstract::getRenderBasePath();
        $this->assertIsString($path);
        $this->assertStringContainsString('rendered', $path);
    }

    // -----------------------------------------------------------------------
    // _htmlToPlainText
    // -----------------------------------------------------------------------

    #[DataProvider('htmlToPlainTextDataProvider')]
    public function testHtmlToPlainText(string $html, string $expected): void
    {
        $renderer = $this->makeRenderer();
        $this->assertSame($expected, $renderer->callHtmlToPlainText($html));
    }

    public static function htmlToPlainTextDataProvider(): array
    {
        $eol = PHP_EOL;
        return [
            'no html'                   => ['plain text',                      'plain text'],
            'br tag converted'          => ['line one<br>line two',            "line one{$eol}line two"],
            'br self-close converted'   => ['line one<br />line two',          "line one{$eol}line two"],
            'closing p converted'       => ['<p>para one</p><p>para two</p>',  "para one{$eol}{$eol}para two{$eol}{$eol}"],
            'nbsp decoded'              => ['word&nbsp;word',                  'word word'],
            'html entities decoded'     => ['faith &amp; hope',               'faith & hope'],
            'tags stripped'             => ['<b>bold</b> text',                'bold text'],
            'windows newlines removed'  => ["line\r\nbreak",                   'linebreak'],
        ];
    }

    public function testHtmlToPlainTextUsesCustomLineSeparator(): void
    {
        $renderer = $this->makeRenderer();
        $result   = $renderer->callHtmlToPlainText('line one<br />line two', ' | ');
        $this->assertSame('line one | line two', $result);
    }
}
