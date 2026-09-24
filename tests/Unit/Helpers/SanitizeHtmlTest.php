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
     * Strong's 'tvm' is NULL for most definitions, and _formatStrongs() feeds it straight in,
     * so NULL has to be accepted rather than fatal.
     *
     * It answers NULL rather than '': the columns this guards are nullable and the API has
     * always reported an absent one as null, so inventing an empty string here would change
     * the shape a client sees.
     */
    public function testSanitizeHtmlAnswersNullWithNull(): void
    {
        $this->assertNull(Helpers::sanitizeHtml(null));
    }

    /** The empty string is absent too - one shape for "nothing", not two. */
    public function testSanitizeHtmlAnswersTheEmptyStringWithNull(): void
    {
        $this->assertNull(Helpers::sanitizeHtml(''));
    }

    /**
     * '0' is content, not absence. A falsey test rather than an explicit empty-string one
     * would drop a field whose entire value is a zero.
     */
    public function testSanitizeHtmlKeepsAZeroString(): void
    {
        $this->assertSame('0', Helpers::sanitizeHtml('0'));
    }

    /**
     * A value that reached the purifier and was emptied by it is not absent - it held
     * something, all of which was refused - so it stays the empty string.
     */
    public function testAValueEmptiedByThePurifierIsNotNull(): void
    {
        $this->assertSame('', Helpers::sanitizeHtml('<script>alert(1)</script>'));
        $this->assertSame('', Helpers::sanitizeHtml('    '));
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
    // External resources - what the markup makes the reader's browser fetch
    // -----------------------------------------------------------------------

    /**
     * Dropping 'img' from an allowlist does not on its own stop the markup loading a third
     * party. HTMLPurifier filters CSS declarations against its own property whitelist, and
     * the URI-valued properties are on it - so 'background-image:url(...)' rode out through
     * span[style] and made every consumer of the field fetch the URL anyway.
     *
     * Closed with URI.DisableExternalResources rather than by dropping 'style', which would
     * also have cost the editor its alignment and its colours.
     *
     * @param string $css
     */
    #[DataProvider('externalResourceCssDataProvider')]
    public function testTheApiSanitizersRefuseCssLoadedExternalResources(string $css): void
    {
        $html = '<span style="' . $css . '">x</span>';

        $this->assertSame('<span>x</span>', Helpers::sanitizeHtml($html), 'sanitizeHtml: ' . $css);
        $this->assertSame('<span>x</span>', Helpers::sanitizeEditorHtmlStrict($html), 'sanitizeEditorHtmlStrict: ' . $css);
    }

    public static function externalResourceCssDataProvider(): array
    {
        return [
            'background image'   => ['background-image:url(http://evil.example/t.png)'],
            'background short'   => ['background:url(http://evil.example/t.png)'],
            'list style image'   => ['list-style-image:url(http://evil.example/t.png)'],
            'protocol relative'  => ['background-image:url(//evil.example/t.png)'],
            'https'              => ['background-image:url(https://evil.example/t.png)'],
        ];
    }

    /** The CSS that loads nothing is untouched by any of it. */
    public function testTheApiSanitizersKeepOrdinaryCssDeclarations(): void
    {
        $html = '<span style="text-align:center;color:#c00">x</span>';

        $this->assertStringContainsString('text-align:center', Helpers::sanitizeHtml($html));
        $this->assertStringContainsString('color:#c00', Helpers::sanitizeEditorHtmlStrict($html));
    }

    /**
     * The editor is the one allowlist that may load one: an administrator inserts images
     * through the CKEditor image plugin and reads them back through the same accessor, so
     * turning external resources off there would blank every one of them on the next Save.
     */
    public function testSanitizeEditorHtmlStillLoadsWhatTheEditorInserts(): void
    {
        $remote = Helpers::sanitizeEditorHtml('<img src="https://e.test/logo.png" alt="Logo">');

        $this->assertStringContainsString('https://e.test/logo.png', $remote);

        $css = Helpers::sanitizeEditorHtml('<span style="background-image:url(https://e.test/bg.png)">x</span>');

        $this->assertStringContainsString('e.test/bg.png', $css);
    }

    // -----------------------------------------------------------------------
    // Frame targets - a link that opens in a new tab
    // -----------------------------------------------------------------------

    /**
     * HTMLPurifier drops 'target' whatever the allowlist says unless Attr.AllowedFrameTargets
     * names the value, so naming 'target' in HTML.Allowed and stopping there is dead weight -
     * the attribute was stripped from the generated copyright statement and from the editor
     * alike, and the editor then saved the loss back over the original.
     *
     * @param string $method
     */
    #[DataProvider('sanitizerDataProvider')]
    public function testAnchorsKeepAnAllowedFrameTarget(string $method): void
    {
        $sanitized = Helpers::$method('<a href="https://e.test/l">license</a>');

        $this->assertStringNotContainsString('target', $sanitized, $method . ': a link without one must not gain one');

        $sanitized = Helpers::$method('<a href="https://e.test/l" target="_blank">license</a>');

        $this->assertStringContainsString('target="_blank"', $sanitized, $method);
    }

    /**
     * And the purifier writes the rel itself, on every allowlist - a new tab opened without
     * it can reach back through window.opener.
     *
     * @param string $method
     */
    #[DataProvider('sanitizerDataProvider')]
    public function testATargetedAnchorCarriesTheRelThatClosesTheOpener(string $method): void
    {
        $sanitized = Helpers::$method('<a href="https://e.test/l" target="_blank">license</a>');

        $this->assertStringContainsString('noopener', $sanitized, $method);
        $this->assertStringContainsString('noreferrer', $sanitized, $method);
    }

    /**
     * '_NEW' is not a frame target the spec defines and the purifier refuses it even when it
     * is named in Attr.AllowedFrameTargets, so the links that meant it say '_blank' - see
     * Copyright::getProcessedCopyrightStatement().
     *
     * @param string $method
     */
    #[DataProvider('sanitizerDataProvider')]
    public function testAnUndefinedFrameTargetIsDropped(string $method): void
    {
        $sanitized = Helpers::$method('<a href="https://e.test/l" target="_NEW">license</a>');

        $this->assertStringContainsString('https://e.test/l', $sanitized, $method);
        $this->assertStringNotContainsStringIgnoringCase('_NEW', $sanitized, $method);
        $this->assertStringNotContainsString('target', $sanitized, $method);
    }

    /** A target is not a way into a scheme the sanitizer would otherwise refuse. */
    #[DataProvider('sanitizerDataProvider')]
    public function testAFrameTargetDoesNotRescueAJavascriptHref(string $method): void
    {
        $sanitized = Helpers::$method('<a href="javascript:alert(1)" target="_blank">x</a>');

        $this->assertStringNotContainsStringIgnoringCase('javascript:', $sanitized, $method);
    }

    public static function sanitizerDataProvider(): array
    {
        return [
            'api'          => ['sanitizeHtml'],
            'editor'       => ['sanitizeEditorHtml'],
            'editor strict'=> ['sanitizeEditorHtmlStrict'],
        ];
    }

    // -----------------------------------------------------------------------
    // sanitizeEditorHtml - what an administrator typed
    // -----------------------------------------------------------------------

    /**
     * The CKEditor build in admin/postconfig.blade.php inserts these, and the editor reads
     * the column back through the accessor that sanitizes it - so anything the allowlist
     * drops is gone from the editor when the page loads and written over the original on the
     * next Save. Against SANITIZE_HTML_ALLOWED that lost every one of them.
     *
     * @param string $html
     * @param string $expected_fragment
     */
    #[DataProvider('editorMarkupDataProvider')]
    public function testSanitizeEditorHtmlKeepsWhatTheEditorInserts(string $html, string $expected_fragment): void
    {
        $this->assertStringContainsString($expected_fragment, Helpers::sanitizeEditorHtml($html));
    }

    public static function editorMarkupDataProvider(): array
    {
        return [
            'image'          => ['<img src="/logo.png" alt="Logo">', '<img src="/logo.png"'],
            'image width'    => ['<img src="/l.png" width="120">',   'width="120"'],
            'horizontal rule'=> ['<p>a</p><hr><p>b</p>',             '<hr />'],
            'strikethrough'  => ['<s>struck</s>',                    '<s>struck</s>'],
            'legacy strike'  => ['<strike>struck</strike>',          '<strike>struck</strike>'],
            'deletion'       => ['<del>removed</del>',               '<del>removed</del>'],
            'insertion'      => ['<ins>added</ins>',                 '<ins>added</ins>'],
            'inline code'    => ['<code>x = 1</code>',               '<code>x = 1</code>'],
            'code block'     => ['<pre>block</pre>',                 '<pre>block</pre>'],
            'block quote'    => ['<blockquote><p>q</p></blockquote>','<blockquote>'],
            'class'          => ['<p class="lead">lead</p>',         'class="lead"'],
            'alignment'      => ['<p style="text-align:center">c</p>', 'text-align:center'],
            'indent'         => ['<p style="margin-left:40px">i</p>',  'margin-left:40px'],
        ];
    }

    /** None of these reach SANITIZE_HTML_ALLOWED, which is the whole point of the split. */
    #[DataProvider('editorMarkupDataProvider')]
    public function testTheApiAllowlistIsStillNarrower(string $html, string $expected_fragment): void
    {
        $this->assertStringNotContainsString($expected_fragment, Helpers::sanitizeHtml($html));
    }

    /**
     * HTMLPurifier has no definition for 'figure', so the wrapper goes - but the picture
     * inside it is the thing that must not, and it survives on its own.
     */
    public function testSanitizeEditorHtmlKeepsTheImageOutOfACkeditorFigure(): void
    {
        $sanitized = Helpers::sanitizeEditorHtml('<figure class="image"><img src="/l.png"><figcaption>Cap</figcaption></figure>');

        $this->assertStringContainsString('<img src="/l.png"', $sanitized);
        $this->assertStringContainsString('Cap', $sanitized);
    }

    /** 'mark' is undefined too; the highlighted words stay, the element does not. */
    public function testSanitizeEditorHtmlKeepsTheTextInsideAnUnsupportedElement(): void
    {
        $sanitized = Helpers::sanitizeEditorHtml('<p>a <mark>highlit</mark> word</p>');

        $this->assertStringContainsString('highlit', $sanitized);
        $this->assertStringNotContainsString('<mark', $sanitized);
    }

    /**
     * The editor allowlist is wider, not weaker. Everything sanitizeHtml() refuses it refuses
     * too - it guards a page that resources/views/docs/{tos,privacy}.php echo unescaped.
     *
     * @param string $html
     */
    #[DataProvider('editorVectorDataProvider')]
    public function testSanitizeEditorHtmlStillRefusesTheVectors(string $html): void
    {
        $sanitized = Helpers::sanitizeEditorHtml($html);

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $sanitized);
        $this->assertStringNotContainsString('<iframe', $sanitized);
    }

    public static function editorVectorDataProvider(): array
    {
        return [
            'script'        => ['<script>alert(1)</script>'],
            'image handler' => ['<img src=x onerror=alert(1)>'],
            'click handler' => ['<p onclick="steal()">x</p>'],
            'js href'       => ['<a href="javascript:alert(1)">x</a>'],
            'iframe'        => ['<iframe src="//evil.test"></iframe>'],
            'js image src'  => ['<img src="javascript:alert(1)">'],
        ];
    }

    /**
     * The round trip the editor performs: the accessor sanitizes, the administrator saves it
     * back unchanged, the accessor sanitizes again. Unless that is a fixed point the document
     * erodes a little on every save.
     *
     * @param string $html
     */
    #[DataProvider('editorMarkupDataProvider')]
    public function testSanitizeEditorHtmlIsIdempotent(string $html): void
    {
        $once = Helpers::sanitizeEditorHtml($html);

        $this->assertSame($once, Helpers::sanitizeEditorHtml($once));
    }

    /**
     * Same guard as testEveryWhitelistedElementIsSupportedByThePurifier(), for the editor
     * allowlist - naming 'figure', 'figcaption' or 'mark' there raises "Element 'x' is not
     * supported" on every definition build and strips them anyway.
     */
    public function testEveryEditorAllowlistElementIsSupportedByThePurifier(): void
    {
        $raised = [];

        set_error_handler(function ($errno, $message) use (&$raised) {
            $raised[] = $message;

            return TRUE;
        });

        try {
            Helpers::sanitizeEditorHtml('<p>trigger the definition build</p>');
        }
        finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised, 'HTMLPurifier rejected an element in SANITIZE_EDITOR_HTML_ALLOWED');
    }

    public function testSanitizeEditorHtmlAnswersNullWithNull(): void
    {
        $this->assertNull(Helpers::sanitizeEditorHtml(null));
        $this->assertNull(Helpers::sanitizeEditorHtml(''));
    }

    /** The same two edges as the API sanitizer - see testSanitizeHtmlKeepsAZeroString(). */
    public function testSanitizeEditorHtmlKeepsAZeroStringAndAnEmptiedValue(): void
    {
        $this->assertSame('0', Helpers::sanitizeEditorHtml('0'));
        $this->assertSame('', Helpers::sanitizeEditorHtml('<script>alert(1)</script>'));
    }

    // -----------------------------------------------------------------------
    // sanitizeEditorHtmlStrict - editor-written HTML on its way out of the API
    // -----------------------------------------------------------------------

    /**
     * Parses an HTML.Allowed specification into element => [attribute, ...].
     *
     * @param string $allowed
     * @return array<string, string[]>
     */
    private static function parseAllowlist(string $allowed): array
    {
        $parsed = [];

        foreach(explode(',', $allowed) as $entry) {
            $entry = trim($entry);

            if($entry === '') {
                continue;
            }

            if(preg_match('/^([a-z0-9]+)\[(.+)\]$/i', $entry, $matches)) {
                $parsed[$matches[1]] = explode('|', $matches[2]);
            }
            else {
                $parsed[$entry] = [];
            }
        }

        return $parsed;
    }

    /**
     * The strict allowlist is the editor allowlist minus what the public API will not carry,
     * so nothing may appear on it that the editor list does not already permit - otherwise a
     * column could leave through the API in a shape the editor itself would have refused.
     */
    public function testTheStrictAllowlistIsASubsetOfTheEditorAllowlist(): void
    {
        $editor = self::parseAllowlist(Helpers::SANITIZE_EDITOR_HTML_ALLOWED);
        $strict = self::parseAllowlist(Helpers::SANITIZE_EDITOR_HTML_ALLOWED_STRICT);

        foreach($strict as $element => $attributes) {
            $this->assertArrayHasKey($element, $editor, $element . ' is not on the editor allowlist');

            foreach($attributes as $attribute) {
                $this->assertContains($attribute, $editor[$element], $element . '[' . $attribute . ']');
            }
        }
    }

    /**
     * The image is what the split is for. A description imported with a module carries the
     * publisher's badge - bibles/modules ship '<img src="eBible.org_certified.jpg">' inside
     * one - and echoing it into /api/bibles makes every consumer that renders the field fetch
     * a third-party URL. The editor keeps it so an administrator can see what they typed; the
     * API does not.
     */
    public function testSanitizeEditorHtmlStrictDropsTheImage(): void
    {
        $html = '<p>Before</p><a href="https://e.test/c"><img src="https://e.test/badge.jpg" alt="Certified"></a><p>After</p>';

        $this->assertStringContainsString('<img', Helpers::sanitizeEditorHtml($html), 'The editor allowlist should have kept the image');

        $strict = Helpers::sanitizeEditorHtmlStrict($html);

        $this->assertStringNotContainsString('<img', $strict);
        $this->assertStringNotContainsString('badge.jpg', $strict);
        $this->assertStringContainsString('Before', $strict);
        $this->assertStringContainsString('After', $strict);
    }

    /**
     * Narrower than the editor allowlist, but not narrower than it needs to be - a copyright
     * statement is mostly this, and losing it would change what a Bible listing reads like.
     *
     * @param string $html
     * @param string $expected_fragment
     */
    #[DataProvider('strictMarkupDataProvider')]
    public function testSanitizeEditorHtmlStrictKeepsTheSafeFormatting(string $html, string $expected_fragment): void
    {
        $this->assertStringContainsString($expected_fragment, Helpers::sanitizeEditorHtmlStrict($html));
    }

    public static function strictMarkupDataProvider(): array
    {
        return [
            'link'            => ['<a href="https://e.test/l">license</a>', '<a href="https://e.test/l">license</a>'],
            'emphasis'        => ['<p>a <strong>b</strong> <em>c</em></p>',  '<strong>b</strong>'],
            'heading'         => ['<h2>Title</h2>',                         '<h2>Title</h2>'],
            'list'            => ['<ul><li>one</li></ul>',                  '<li>one</li>'],
            'horizontal rule' => ['<p>a</p><hr><p>b</p>',                   '<hr />'],
            'strikethrough'   => ['<s>struck</s>',                          '<s>struck</s>'],
            'deletion'        => ['<del>removed</del>',                     '<del>removed</del>'],
            'insertion'       => ['<ins>added</ins>',                       '<ins>added</ins>'],
            'block quote'     => ['<blockquote><p>q</p></blockquote>',      '<blockquote>'],
            'inline code'     => ['<code>x = 1</code>',                     '<code>x = 1</code>'],
            'code block'      => ['<pre><code>x = 1</code></pre>',          '<pre><code>x = 1</code></pre>'],
            'table'           => ['<table><tr><td>c</td></tr></table>',     '<td>c</td>'],
            'class'           => ['<p class="lead">lead</p>',               'class="lead"'],
            'line break'      => ['<p>a<br>b</p>',                          '<br />'],
        ];
    }

    /**
     * Wider than SANITIZE_HTML_ALLOWED is not weaker than it. Everything sanitizeHtml()
     * refuses the strict editor allowlist refuses too.
     *
     * @param string $html
     */
    #[DataProvider('editorVectorDataProvider')]
    public function testSanitizeEditorHtmlStrictStillRefusesTheVectors(string $html): void
    {
        $sanitized = Helpers::sanitizeEditorHtmlStrict($html);

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('onclick', $sanitized);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $sanitized);
        $this->assertStringNotContainsString('<iframe', $sanitized);
        $this->assertStringNotContainsString('<img', $sanitized);
    }

    /**
     * The API sanitizes on the way out of a column the editor already sanitized, so the two
     * run one after the other on the same value and the second must not erode the first.
     *
     * @param string $html
     */
    #[DataProvider('strictMarkupDataProvider')]
    public function testSanitizeEditorHtmlStrictIsIdempotent(string $html): void
    {
        $once = Helpers::sanitizeEditorHtmlStrict(Helpers::sanitizeEditorHtml($html));

        $this->assertSame($once, Helpers::sanitizeEditorHtmlStrict($once));
    }

    /**
     * Same guard as testEveryEditorAllowlistElementIsSupportedByThePurifier(), for the strict
     * allowlist - an element HTMLPurifier has no definition for warns on every build and is
     * stripped regardless, so naming one here buys nothing and makes noise.
     */
    public function testEveryStrictAllowlistElementIsSupportedByThePurifier(): void
    {
        $raised = [];

        set_error_handler(function ($errno, $message) use (&$raised) {
            $raised[] = $message;

            return TRUE;
        });

        try {
            Helpers::sanitizeEditorHtmlStrict('<p>trigger the definition build</p>');
        }
        finally {
            restore_error_handler();
        }

        $this->assertSame([], $raised, 'HTMLPurifier rejected an element in SANITIZE_EDITOR_HTML_ALLOWED_STRICT');
    }

    /** The same edges as the other two sanitizers - see testSanitizeHtmlKeepsAZeroString(). */
    public function testSanitizeEditorHtmlStrictHandlesTheAbsentValues(): void
    {
        $this->assertNull(Helpers::sanitizeEditorHtmlStrict(null));
        $this->assertNull(Helpers::sanitizeEditorHtmlStrict(''));
        $this->assertSame('0', Helpers::sanitizeEditorHtmlStrict('0'));
        $this->assertSame('', Helpers::sanitizeEditorHtmlStrict('<script>alert(1)</script>'));
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
     * The purifier rewrites a bare '&' to '&amp;', which is right for an HTML document.
     *
     * Verse text is not one, and the 'italics' field indexes it by character offset, so the
     * four-character expansion used to move every offset past the ampersand - 8,015 of the
     * installed verses carry one. Helpers used to hold them out behind a sentinel for that
     * reason; Engine::_processBibleText() strips tags instead of purifying now, so verse text
     * never reaches this method and the sentinel is gone.
     *
     * Pinned because it is the behaviour that made the sentinel necessary: anything routed
     * through here still gets an HTML document's treatment.
     */
    public function testSanitizeHtmlStillExpandsABareAmpersand(): void
    {
        $this->assertSame('a &amp; b', Helpers::sanitizeHtml('a & b'));
    }

    /** And an existing entity is left as one, rather than double-encoded. */
    public function testSanitizeHtmlDoesNotDoubleEncodeAnEntity(): void
    {
        $this->assertSame('a &amp; b', Helpers::sanitizeHtml('a &amp; b'));
    }

    // -----------------------------------------------------------------------
    // Flattening a document PCRE gives up on
    // -----------------------------------------------------------------------

    /**
     * preg_replace() answers NULL rather than a string when the pattern exhausts
     * pcre.backtrack_limit, and the lazy '.*?' in the <head> pattern does exactly that on a
     * description of roughly a megabyte at the stock limit. Unhandled, that NULL becomes the
     * subject of the next replacement, coerces to '', and empties the description outright -
     * which is what this asserts against, since the <head> pattern is not the last one. The
     * last failing instead returns NULL through a ': string' declaration, a TypeError.
     *
     * The limit is lowered here instead of building a megabyte of markup: at 10 it is the
     * <head> pattern alone that gives up, which is the same failure the size would produce.
     * Flattening then does not happen, and not flattening is fine - the purifier drops the
     * scaffolding tags itself. Losing the value is what must not happen.
     */
    public function testSanitizeHtmlSurvivesAPcreBacktrackFailure(): void
    {
        $html = '<html><head><title>A title long enough to backtrack</title></head>'
            . '<body><p>Module desc</p></body></html><br />Imported credit';

        $original_limit = ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '10');

        try {
            $sanitized = Helpers::sanitizeHtml($html);
        } finally {
            ini_set('pcre.backtrack_limit', $original_limit);
        }

        $this->assertStringContainsString('<p>Module desc</p>', $sanitized);
        $this->assertStringContainsString('Imported credit', $sanitized);
    }

    /** The ordinary path is unchanged by the fallbacks - a document still flattens. */
    public function testTheBacktrackFallbackDoesNotDisturbTheOrdinaryCase(): void
    {
        $this->assertSame(
            '<p>Body</p>',
            Helpers::sanitizeHtml('<!DOCTYPE html><html><head><title>T</title></head><body><p>Body</p></body></html>')
        );
    }
}
