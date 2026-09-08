<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;

/**
 * BSS-290: Helpers::sanitizeHtml() is the single gate every HTML-bearing API field now passes
 * through (verse text, copyright statements, Strong's entries), and
 * Helpers::convertHtmlToMarkdown() is the API v3 variant that hands back Markdown instead.
 *
 * The whitelist is what makes the gate a security control rather than a formatting pass, so
 * both the tags that must survive and the vectors that must not are pinned here. Both helpers
 * are pure - HTMLPurifier and the Markdown converter need no application, database or config.
 */
class SanitizeHtmlTest extends TestCase
{
    // -----------------------------------------------------------------------
    // sanitizeHtml - the allowed whitelist
    // -----------------------------------------------------------------------

    #[DataProvider('allowedMarkupDataProvider')]
    public function testSanitizeHtmlKeepsAllowedMarkup(string $html, string $expected): void
    {
        $this->assertSame($expected, Helpers::sanitizeHtml($html));
    }

    public static function allowedMarkupDataProvider(): array
    {
        return [
            'bold'          => ['<b>bold</b>',                   '<b>bold</b>'],
            'italic'        => ['<i>ital</i>',                   '<i>ital</i>'],
            'underline'     => ['<u>under</u>',                  '<u>under</u>'],
            'paragraph'     => ['<p>para</p>',                   '<p>para</p>'],
            'div'           => ['<div>d</div>',                  '<div>d</div>'],
            'line break'    => ['a<br>b',                        'a<br />b'],
            'unordered list'=> ['<ul><li>one</li></ul>',         '<ul><li>one</li></ul>'],
            'ordered list'  => ['<ol><li>one</li></ol>',         '<ol><li>one</li></ol>'],
            'anchor href'   => ['<a href="https://example.com">link</a>', '<a href="https://example.com">link</a>'],
            'plain text'    => ['plain text',                    'plain text'],
            'entities kept' => ['Alpha &amp; Omega',             'Alpha &amp; Omega'],
            'empty string'  => ['',                              ''],
            // Widened whitelist: imported descriptions and the Terms of Service use these.
            'strong'        => ['<strong>s</strong>',            '<strong>s</strong>'],
            'em'            => ['<em>e</em>',                    '<em>e</em>'],
            'span'          => ['<span>span</span>',             '<span>span</span>'],
            'h1'            => ['<h1>head</h1>',                 '<h1>head</h1>'],
            'h6'            => ['<h6>head</h6>',                 '<h6>head</h6>'],
            'subscripts'    => ['<sub>x</sub><sup>y</sup><small>z</small>', '<sub>x</sub><sup>y</sup><small>z</small>'],
            'table'         => [
                '<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>C</td></tr></tbody></table>',
                '<table><thead><tr><th>H</th></tr></thead><tbody><tr><td>C</td></tr></tbody></table>',
            ],
        ];
    }

    /** The output is trimmed, so a field padded in the source does not ship its padding. */
    public function testSanitizeHtmlTrimsItsOutput(): void
    {
        $this->assertSame('<p>trim me</p>', Helpers::sanitizeHtml('   <p>trim me</p>   '));
        $this->assertSame('', Helpers::sanitizeHtml('    '));
    }

    /**
     * Every element named in the whitelist has to be one HTMLPurifier actually defines. An
     * element it does not know - 'mark' is one - raises "Element 'x' is not supported" on the
     * first purify() and is stripped anyway, so the entry buys nothing and adds a warning.
     */
    public function testEveryWhitelistedElementIsSupportedByThePurifier(): void
    {
        $raised = [];

        set_error_handler(function ($errno, $message) use (&$raised) {
            $raised[] = $message;

            return TRUE;
        });

        try {
            Helpers::sanitizeHtml('<p>trigger the definition build</p>');
        }
        finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised, 'HTMLPurifier rejected an element in SANITIZE_HTML_ALLOWED');
    }

    /**
     * The verse text the highlighter and the renderers care about most: <i> marks translator
     * italics and <b> is the default highlight tag, so neither may be stripped.
     */
    public function testSanitizeHtmlKeepsItalicsAndHighlightMarkupInVerseText(): void
    {
        $verse = 'And God said, <i>Let</i> there be <b>light</b>: and there was light.';

        $this->assertSame($verse, Helpers::sanitizeHtml($verse));
    }

    // -----------------------------------------------------------------------
    // sanitizeHtml - what must not survive
    // -----------------------------------------------------------------------

    #[DataProvider('strippedMarkupDataProvider')]
    public function testSanitizeHtmlStripsEverythingElse(string $html, string $expected): void
    {
        $this->assertSame($expected, Helpers::sanitizeHtml($html));
    }

    public static function strippedMarkupDataProvider(): array
    {
        return [
            // Script and its payload both go - the text of a <script> is not content.
            'script tag'        => ['<script>alert(1)</script>',            ''],
            'inline event img'  => ['<img src=x onerror=alert(1)>',         ''],
            'iframe'            => ['<iframe src="//evil.test"></iframe>',  ''],
            'style block'       => ['<style>body{display:none}</style>',    ''],
            'form'              => ['<form action="//evil.test"></form>',   ''],
            // Not on the whitelist, but the text they wrap is content and is kept.
            'mark unwrapped'       => ['<mark>m</mark>',                    'm'],
            'blockquote unwrapped' => ['<blockquote>q</blockquote>',        'q'],
            // Document scaffolding from imported Bible descriptions.
            'document wrapper'  => ['<html><head><meta charset="UTF-8"></head><body><b>X</b>text</body></html>', '<b>X</b>text'],
        ];
    }

    /**
     * The two attribute-level vectors: an event handler on an otherwise allowed tag, and a
     * javascript: URI in the one attribute the whitelist permits.
     */
    public function testSanitizeHtmlStripsEventHandlersFromAllowedTags(): void
    {
        $purified = Helpers::sanitizeHtml('<a href="https://example.com" onclick="steal()">link</a>');

        $this->assertStringNotContainsString('onclick', $purified);
        $this->assertStringContainsString('href="https://example.com"', $purified);
    }

    public function testSanitizeHtmlStripsJavascriptUris(): void
    {
        $purified = Helpers::sanitizeHtml('<a href="javascript:alert(1)">bad</a>');

        $this->assertStringNotContainsString('javascript:', $purified);
        $this->assertStringContainsString('bad', $purified);
    }

    /**
     * Strong's 'tvm' is NULL for most definitions, and _formatStrongs() feeds it straight in.
     * The declared return type is string, so the NULL short-circuit has to answer '' - handing
     * the argument straight back raises "Return value must be of type string, null returned".
     */
    public function testSanitizeHtmlAcceptsNull(): void
    {
        $this->assertSame('', Helpers::sanitizeHtml(null));
    }

    // -----------------------------------------------------------------------
    // The style attribute
    // -----------------------------------------------------------------------

    /**
     * span[style] is on the whitelist, so the CSS itself becomes the attack surface.
     * HTMLPurifier filters the declarations against its own property whitelist; what matters
     * here is that the filtering is on at all.
     */
    public function testSanitizeHtmlFiltersStyleDeclarations(): void
    {
        $this->assertStringContainsString('color', Helpers::sanitizeHtml('<span style="color:red">c</span>'));

        foreach(['position:fixed', 'behavior:url(x.htc)', 'width:expression(alert(1))'] as $css) {
            $purified = Helpers::sanitizeHtml('<span style="' . $css . '">x</span>');

            $this->assertSame('<span>x</span>', $purified, $css . ' survived');
        }
    }

    public function testSanitizeHtmlStripsEventHandlersFromTables(): void
    {
        $purified = Helpers::sanitizeHtml('<table onclick="steal()"><tr><td>c</td></tr></table>');

        $this->assertStringNotContainsString('onclick', $purified);
        $this->assertStringContainsString('<td>c</td>', $purified);
    }

    // -----------------------------------------------------------------------
    // convertHtmlToMarkdown - the API v3 output format
    // -----------------------------------------------------------------------

    #[DataProvider('markdownDataProvider')]
    public function testConvertHtmlToMarkdown(string $html, string $expected): void
    {
        $this->assertSame($expected, Helpers::convertHtmlToMarkdown($html));
    }

    public static function markdownDataProvider(): array
    {
        return [
            'bold'         => ['<b>bold</b>',                   '**bold**'],
            'italic'       => ['<i>ital</i>',                   '*ital*'],
            'anchor'       => ['<a href="https://example.com">link</a>', '[link](https://example.com)'],
            'list'         => ['<ul><li>one</li><li>two</li></ul>', "- one\n- two"],
            'paragraphs'   => ['<p>one</p><p>two</p>',          "one\n\ntwo"],
            'plain text'   => ['plain text',                    'plain text'],
            'empty string' => ['',                              ''],
        ];
    }

    /**
     * The conversion is sanitize-then-convert, so the whitelist still applies: nothing a raw
     * HTML response would have blocked may reappear in the Markdown one.
     */
    public function testConvertHtmlToMarkdownSanitizesBeforeConverting(): void
    {
        $this->assertSame('', Helpers::convertHtmlToMarkdown('<script>alert(1)</script>'));
        $this->assertSame('', Helpers::convertHtmlToMarkdown('<img src=x onerror=alert(1)>'));
        $this->assertStringNotContainsString('onclick', Helpers::convertHtmlToMarkdown('<a href="https://example.com" onclick="steal()">link</a>'));
    }

    public function testConvertHtmlToMarkdownAcceptsNull(): void
    {
        $this->assertSame('', Helpers::convertHtmlToMarkdown(null));
    }
}
