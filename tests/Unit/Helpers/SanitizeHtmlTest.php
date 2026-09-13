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

    /**
     * Engine::_processHtml() and its subclass hooks are handed values that have already been
     * purified, so they turn the sanitize step off. It must be the only thing that changes -
     * the Markdown is identical either way.
     */
    public function testConvertHtmlToMarkdownCanSkipTheRedundantSanitize(): void
    {
        $sanitized = Helpers::sanitizeHtml('<b>bold</b> and <i>ital</i>');

        $this->assertSame(
            Helpers::convertHtmlToMarkdown($sanitized, TRUE),
            Helpers::convertHtmlToMarkdown($sanitized, FALSE)
        );

        $this->assertSame('', Helpers::convertHtmlToMarkdown(null, FALSE));
        $this->assertSame('', Helpers::convertHtmlToMarkdown('', FALSE));
    }

    /** The converter is held between calls, so repeated conversions must not drift. */
    public function testConvertHtmlToMarkdownIsStableAcrossCalls(): void
    {
        $first = Helpers::convertHtmlToMarkdown('<p>one</p><ul><li>a</li><li>b</li></ul>');

        for($i = 0; $i < 5; $i++) {
            $this->assertSame($first, Helpers::convertHtmlToMarkdown('<p>one</p><ul><li>a</li><li>b</li></ul>'));
        }
    }

    // -----------------------------------------------------------------------
    // Whole documents - the import credit that follows </html>
    // -----------------------------------------------------------------------

    /**
     * HTMLPurifier discards everything after </html>, and Importers\MyBible stores exactly
     * that shape: a full document from the module metadata with the import credit appended
     * after it. Flattening the document to a fragment first is what keeps the credit.
     */
    public function testSanitizeHtmlKeepsContentAppendedAfterADocument(): void
    {
        $html = '<html><head><title>T</title></head><body><p>Module desc</p></body></html>'
            . '<br /><br />Imported from <a href="http://unbound.biola.edu/">The Unbound Bible</a>';

        $sanitized = Helpers::sanitizeHtml($html);

        $this->assertStringContainsString('<p>Module desc</p>', $sanitized);
        $this->assertStringContainsString('Imported from', $sanitized);
        $this->assertStringContainsString('unbound.biola.edu', $sanitized);
    }

    /** The document scaffolding itself is dropped - only its body content is kept. */
    public function testSanitizeHtmlDropsTheDocumentScaffolding(): void
    {
        $sanitized = Helpers::sanitizeHtml(
            '<!DOCTYPE html><html><head><title>Title</title><style>body{color:red}</style></head>'
            . '<body><p>Body</p></body></html>'
        );

        $this->assertSame('<p>Body</p>', $sanitized);
        $this->assertStringNotContainsString('Title', $sanitized);
        $this->assertStringNotContainsString('color:red', $sanitized);
    }

    /** Flattening must not disturb a value that was already a fragment. */
    public function testSanitizeHtmlLeavesOrdinaryFragmentsAlone(): void
    {
        $this->assertSame('<p>Just a <b>fragment</b></p>', Helpers::sanitizeHtml('<p>Just a <b>fragment</b></p>'));
    }

    // -----------------------------------------------------------------------
    // Bare ampersands
    // -----------------------------------------------------------------------

    /**
     * A bare '&' is not valid HTML and the purifier rewrites it to '&amp;'. Verse text is not
     * an HTML document, and the 'italics' field indexes it by character offset, so the
     * four-character expansion moves every offset past the ampersand.
     */
    public function testTheSanitizerWouldOtherwiseExpandABareAmpersand(): void
    {
        $this->assertSame('a &amp; b', Helpers::sanitizeHtml('a & b'));
    }

    public function testBareAmpersandsSurviveSanitizationWhenProtected(): void
    {
        $protected = Helpers::protectBareAmpersands('and was voyde: & darknes was <b>vpon</b>');
        $restored  = Helpers::restoreBareAmpersands(Helpers::sanitizeHtml($protected));

        $this->assertSame('and was voyde: & darknes was <b>vpon</b>', $restored);
    }

    /** Existing entities are left as they are - only a bare '&' is held out. */
    public function testProtectingAmpersandsLeavesExistingEntitiesAlone(): void
    {
        $protected = Helpers::protectBareAmpersands('A & B &amp; C &lt;script&gt; D &#39;E');
        $restored  = Helpers::restoreBareAmpersands(Helpers::sanitizeHtml($protected));

        $this->assertStringContainsString('A & B', $restored);
        $this->assertStringContainsString('&amp;', $restored);
        $this->assertStringContainsString('&lt;script&gt;', $restored);
    }

    /** Protection is not a way past the whitelist. */
    public function testProtectingAmpersandsDoesNotWeakenTheSanitizer(): void
    {
        $protected = Helpers::protectBareAmpersands('a & b <script>alert(1)</script> c');
        $restored  = Helpers::restoreBareAmpersands(Helpers::sanitizeHtml($protected));

        $this->assertStringNotContainsString('<script', $restored);
        $this->assertStringNotContainsString('alert(1)', $restored);
        $this->assertStringContainsString('a & b', $restored);
    }

    /**
     * The sentinel is a private-use codepoint, but a crafted module could still carry one;
     * it is dropped on the way in so it cannot be used to inject an ampersand.
     */
    public function testASentinelInTheSourceTextIsDiscarded(): void
    {
        $restored = Helpers::restoreBareAmpersands(
            Helpers::sanitizeHtml(Helpers::protectBareAmpersands("smuggled \u{E000} here"))
        );

        $this->assertSame('smuggled  here', $restored);
    }

    public function testProtectBareAmpersandsAcceptsNull(): void
    {
        $this->assertSame('', Helpers::protectBareAmpersands(null));
    }
}
