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
        };
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
