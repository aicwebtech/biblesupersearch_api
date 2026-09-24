<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;

/**
 * BSS-290: Helpers::buildHighlightTags() decides how a highlight tag wraps a match, and both
 * SqlSearch::highlightResults() and Passage::highlightContext() go through it so keyword and
 * context highlighting cannot drift apart.
 *
 * An HTML element name has to become a matched open/close pair; a Markdown marker such as '**'
 * is symmetrical and must be emitted verbatim - wrapping it would produce '<**>', which is
 * neither HTML nor Markdown. Pure.
 *
 * Highlighting runs after Helpers::sanitizeHtml(), so the pair this returns reaches the
 * response unpurified: the element name is whitelisted here or it is not emitted at all.
 */
class HighlightTagsTest extends TestCase
{
    #[DataProvider('highlightTagDataProvider')]
    public function testBuildHighlightTags($tag, string $pre, string $post): void
    {
        $this->assertSame([$pre, $post], Helpers::buildHighlightTags($tag));
    }

    public static function highlightTagDataProvider(): array
    {
        return [
            // Bare element names - wrapped into a matched pair.
            'default b'        => ['b',      '<b>',      '</b>'],
            'em'               => ['em',     '<em>',     '</em>'],
            'span'             => ['span',   '<span>',   '</span>'],
            'uppercase B'      => ['B',      '<B>',      '</B>'],
            'strong'           => ['strong', '<strong>', '</strong>'],
            // Off the whitelist - answered with the default element rather than the request.
            'custom element'   => ['high',   '<b>',      '</b>'],
            // A hyphen makes a legal custom element, not a Markdown marker - emitting it
            // verbatim on both sides ran it into the words around the match.
            'hyphenated'       => ['my-tag', '<b>',      '</b>'],
            'trailing hyphen'  => ['b-',     '<b>',      '</b>'],
            // Not a legal element name, but plainly meant as one - highlighted, not echoed.
            'leading digit'    => ['1b',     '<b>',      '</b>'],
            'digits only'      => ['42',     '<b>',      '</b>'],
            // HTML5 inline elements. Verse text never reaches HTMLPurifier, so its HTML 4
            // vocabulary is not what bounds the whitelist.
            'mark'             => ['mark',   '<mark>',   '</mark>'],
            'time'             => ['time',   '<time>',   '</time>'],
            'bdi'              => ['bdi',    '<bdi>',    '</bdi>'],
            // HTML 4 presentational names, kept for the clients that have always sent them.
            'big'              => ['big',    '<big>',    '</big>'],
            'strike'           => ['strike', '<strike>', '</strike>'],
            // Block elements stay off it - they would break the verse out of its own line.
            'heading'          => ['h1',     '<b>',      '</b>'],
            'paragraph'        => ['p',      '<b>',      '</b>'],
            // As do the elements that need an attribute or a parent to mean anything.
            'anchor'           => ['a',      '<b>',      '</b>'],
            'font'             => ['font',   '<b>',      '</b>'],
            'ruby'             => ['ruby',   '<b>',      '</b>'],
            // Markdown and other symmetrical markers - used as-is on both sides.
            'markdown bold'    => ['**',     '**',       '**'],
            'markdown italic'  => ['*',      '*',        '*'],
            'markdown under'   => ['__',     '__',       '__'],
            'backtick'         => ['`',      '`',        '`'],
        ];
    }

    /**
     * A tag that already carries its own angle brackets is not an element name and not a
     * plain-text marker either, so it falls back to the default rather than being wrapped a
     * second time into '<<b>>' or echoed into the response as it stands.
     */
    public function testAnAngleBracketedTagIsNotWrappedAgain(): void
    {
        list($pre, $post) = Helpers::buildHighlightTags('<b>');

        $this->assertSame(['<b>', '</b>'], [$pre, $post]);
        $this->assertStringNotContainsString('<<', $pre);
        $this->assertStringNotContainsString('</<', $post);
    }

    /**
     * Only a marker on HIGHLIGHT_PLAIN_TEXT_MARKERS is echoed; everything else falls back to
     * the default element.
     *
     * An allowlist rather than a character filter, because the marker reaches the response
     * after sanitizeHtml() has run and is never escaped. Excluding the characters that open a
     * tag is not sufficient on its own - a Markdown image payload contains none of them and
     * is executable all the same once a client renders the v3 response - and nothing upstream
     * is filtering either: Engine::sanitizeString() runs strip_tags(), which leaves
     * '<img src=x onerror=...' with an attribute and no closing bracket entirely intact.
     */
    #[DataProvider('rejectedMarkerDataProvider')]
    public function testAMarkerOffTheAllowlistFallsBackToTheDefault(string $tag): void
    {
        $this->assertSame(['<b>', '</b>'], Helpers::buildHighlightTags($tag));
    }

    public static function rejectedMarkerDataProvider(): array
    {
        return [
            'open tag'          => ['<script>'],
            'unclosed tag'      => ['<img src=x onerror=alert(1)'],
            'closing bracket'   => ['>'],
            'entity'            => ['&amp;'],
            'double quote'      => ['"'],
            'single quote'      => ["'"],
            'empty'             => [''],
            // Markdown that renders to something executable. None of these carries a
            // character a tag filter would catch.
            'markdown image'    => ['![x](javascript:alert(1))'],
            'markdown link'     => ['[x](javascript:alert(1))'],
            'markdown autolink' => ['<javascript:alert(1)>'],
            'html entity ref'   => ['&#106;'],
            // The two delimiters SqlSearch::highlightResults() marks matches with internally,
            // before swapping them for whatever pair this resolves to.
            'search alias'      => ['&&'],
            'search wildcard'   => ['%'],
            'wildcard pair'     => ['%%'],
            'alias fragment'    => ['&'],
            // Punctuation that is not a Markdown marker at all.
            'pipes'             => ['||'],
            'colons'            => ['::'],
            'braces'            => ['{{'],
            'dollar'            => ['$1'],
            'backslash'         => ['\\'],
        ];
    }

    /** The allowlist itself: every entry is a marker, and every marker round-trips. */
    public function testEveryAllowedMarkerIsEmittedVerbatim(): void
    {
        foreach(Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS as $marker) {
            $this->assertTrue(Helpers::isPlainTextHighlightMarker($marker), $marker);
            $this->assertSame([$marker, $marker], Helpers::buildHighlightTags($marker), $marker);
        }
    }

    /**
     * DEFAULT_HIGHLIGHT_MARKER backs EngineV3 when config('bss.defaults.highlight_tag_v3') is
     * missing, so it has to be a marker the highlighter will emit verbatim - an element name
     * there would put HTML back into a Markdown response.
     */
    public function testTheDefaultMarkerIsItselfAnAllowedMarker(): void
    {
        $this->assertContains(Helpers::DEFAULT_HIGHLIGHT_MARKER, Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS);
        $this->assertTrue(Helpers::isPlainTextHighlightMarker(Helpers::DEFAULT_HIGHLIGHT_MARKER));
    }

    /**
     * The highlighter's own delimiters cannot appear in an allowed marker, or a caller could
     * forge the bookkeeping SqlSearch::highlightResults() does before substituting the pair.
     */
    public function testNoAllowedMarkerCarriesTheHighlightersOwnDelimiters(): void
    {
        foreach(Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS as $marker) {
            $this->assertStringNotContainsString('&', $marker, $marker);
            $this->assertStringNotContainsString('%', $marker, $marker);
        }
    }

    /** Nor anything that could open an element or an entity. */
    public function testNoAllowedMarkerCarriesMarkup(): void
    {
        foreach(Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS as $marker) {
            $this->assertDoesNotMatchRegularExpression('/[<>&"\'\\\\\\[\\]()]/', $marker, $marker);
        }
    }

    /**
     * The two sides of the decision, asserted directly: EngineV3 asks the same question to
     * decide whether the caller handed it something usable in a Markdown response.
     */
    #[DataProvider('plainTextMarkerDataProvider')]
    public function testIsPlainTextHighlightMarker(string $tag, bool $expected): void
    {
        $this->assertSame($expected, Helpers::isPlainTextHighlightMarker($tag));
    }

    public static function plainTextMarkerDataProvider(): array
    {
        return [
            'markdown bold'   => ['**',       TRUE],
            'markdown italic' => ['*',        TRUE],
            'underscores'     => ['__',       TRUE],
            'backtick'        => ['`',        TRUE],
            'tilde'           => ['~~',       TRUE],
            'element name'    => ['b',        FALSE],
            'custom element'  => ['my-tag',   FALSE],
            'leading digit'   => ['1b',       FALSE],
            'angle bracketed' => ['<b>',      FALSE],
            'ampersand'       => ['&&',       FALSE],
            'wildcard'        => ['%',        FALSE],
            'markdown image'  => ['![x](javascript:alert(1))', FALSE],
            'empty'           => ['',         FALSE],
        ];
    }

    public function testAMarkdownTagIsSymmetrical(): void
    {
        list($pre, $post) = Helpers::buildHighlightTags('**');

        $this->assertSame($pre, $post);
    }

    public function testAnHtmlTagIsNotSymmetrical(): void
    {
        list($pre, $post) = Helpers::buildHighlightTags('b');

        $this->assertNotSame($pre, $post);
    }

    /**
     * The highlight tag is caller-supplied and is emitted after sanitization, so a tag name
     * that names an executable or document-level element is the one real injection vector
     * into verse text. None of them may be echoed back.
     */
    #[DataProvider('rejectedTagDataProvider')]
    public function testAnUnsafeTagNameFallsBackToTheDefault(string $tag): void
    {
        list($pre, $post) = Helpers::buildHighlightTags($tag);

        $this->assertSame(['<b>', '</b>'], [$pre, $post]);
        $this->assertStringNotContainsStringIgnoringCase($tag, $pre);
        $this->assertStringNotContainsStringIgnoringCase($tag, $post);
    }

    public static function rejectedTagDataProvider(): array
    {
        return [
            'script'    => ['script'],
            // Switches the parser to raw text, so an unclosed one swallows the rest of the
            // verse rather than highlighting a word of it.
            'title'     => ['title'],
            'textarea'  => ['textarea'],
            'xmp'       => ['xmp'],
            'noscript'  => ['noscript'],
            'template'  => ['template'],
            'math'      => ['math'],
            // Void - there is no closing tag to wrap a match in.
            'br'        => ['br'],
            'hr'        => ['hr'],
            'wbr'       => ['wbr'],
            'iframe'    => ['iframe'],
            'style'     => ['style'],
            'svg'       => ['svg'],
            'object'    => ['object'],
            'embed'     => ['embed'],
            'img'       => ['img'],
            'form'      => ['form'],
            'body'      => ['body'],
            'uppercase' => ['SCRIPT'],
            'mixed case'=> ['ScRiPt'],
        ];
    }

    /**
     * The whitelist itself: every entry has to be a bare element name the highlighter can
     * wrap a match in, or it would reach the response as something other than a tag.
     */
    public function testEveryWhitelistedElementIsABareElementName(): void
    {
        foreach(Helpers::HIGHLIGHT_TAG_WHITELIST as $tag) {
            $this->assertMatchesRegularExpression('/^[a-z]+[0-9]*$/', $tag, $tag);
            $this->assertSame(['<' . $tag . '>', '</' . $tag . '>'], Helpers::buildHighlightTags($tag), $tag);
        }
    }

    /**
     * And none of them may be an element that cannot hold a highlighted word: a void element
     * has no closing tag, a block element breaks the verse out of its own line, and a
     * raw-text element swallows everything after it.
     */
    public function testNoWhitelistedElementIsVoidBlockOrRawText(): void
    {
        $forbidden = [
            'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta',
            'param', 'source', 'track', 'wbr',
            'address', 'article', 'aside', 'blockquote', 'div', 'dl', 'fieldset', 'figure',
            'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'li',
            'main', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'td', 'th', 'tr', 'ul',
            'iframe', 'math', 'noscript', 'object', 'script', 'style', 'svg', 'template',
            'textarea', 'title', 'xmp',
        ];

        foreach($forbidden as $tag) {
            $this->assertNotContains($tag, Helpers::HIGHLIGHT_TAG_WHITELIST, $tag);
        }
    }

    /**
     * An element and a plain-text marker are answered differently, so nothing may be on both
     * lists - an entry on each would resolve by whichever check runs first.
     */
    public function testTheElementWhitelistAndTheMarkerAllowlistDoNotOverlap(): void
    {
        $this->assertSame(
            [],
            array_intersect(Helpers::HIGHLIGHT_TAG_WHITELIST, Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS)
        );
    }

    /** The default has to be on the whitelist, or every rejected tag would reject itself. */
    public function testTheDefaultTagIsItselfWhitelisted(): void
    {
        $this->assertContains(Helpers::DEFAULT_HIGHLIGHT_TAG, Helpers::HIGHLIGHT_TAG_WHITELIST);
    }

    /** The whitelist is case-insensitive, but the caller's own casing is preserved. */
    public function testTheWhitelistIsCaseInsensitive(): void
    {
        $this->assertSame(['<EM>', '</EM>'], Helpers::buildHighlightTags('EM'));
        $this->assertSame(['<Strong>', '</Strong>'], Helpers::buildHighlightTags('Strong'));
    }

    /**
     * A Markdown marker is not an element name, so the whitelist does not apply to it - the
     * fallback must not swallow the markers API v3 highlights with.
     */
    public function testMarkdownMarkersAreNotSubjectToTheWhitelist(): void
    {
        $this->assertSame(['**', '**'], Helpers::buildHighlightTags('**'));
        $this->assertSame(['__', '__'], Helpers::buildHighlightTags('__'));
    }

    // -----------------------------------------------------------------------
    // The install's configured default
    // -----------------------------------------------------------------------

    /**
     * BSS-290: the whitelist was applied to the configured default as well as to caller input,
     * and only caller input has a deliberate fallback.
     *
     * An install that sets DEFAULT_HIGHLIGHT_TAG to an element the whitelist does not name -
     * 'high', which is what this repo's own UnicodeTest used to send - had every highlight
     * silently rewritten to '<b>', so a front end styling that element lost all highlighting
     * with no error in the response and nothing in the log. The default is the operator's, not
     * a caller's, so it is trusted; it still has to be a bare element name.
     */
    #[DataProvider('configuredDefaultDataProvider')]
    public function testTheConfiguredDefaultIsAcceptedOffTheWhitelist($tag, $default, string $pre, string $post): void
    {
        $this->assertSame([$pre, $post], Helpers::buildHighlightTags($tag, $default));
    }

    public static function configuredDefaultDataProvider(): array
    {
        return [
            // The operator's own element, asked for by name and reached by fallback.
            'configured default requested' => ['high',   'high', '<high>', '</high>'],
            'rejected tag falls back to it'=> ['script', 'high', '<high>', '</high>'],
            'empty tag falls back to it'   => ['',       'high', '<high>', '</high>'],
            // The whitelist still stands on its own.
            'whitelisted tag is untouched' => ['em',     'high', '<em>',   '</em>'],
            // Casing: the whitelist is case-insensitive and the caller's casing is kept, but a
            // fallback emits the default in one spelling rather than the operator's.
            'requested in other casing'    => ['HIGH',   'high', '<HIGH>', '</HIGH>'],
            'configured in other casing'   => ['x',      'High', '<high>', '</high>'],
            // No default given - unchanged from before, the built-in default answers.
            'no configured default'        => ['high',   NULL,   '<b>',    '</b>'],
            // A default that is not an element name cannot be wrapped around a match, so it is
            // ignored and DEFAULT_HIGHLIGHT_TAG answers instead.
            'default is angle bracketed'   => ['high',   '<b>',  '<b>',    '</b>'],
            'default is a markdown image'  => ['high',   '![x](javascript:alert(1))', '<b>', '</b>'],
            'default is a marker'          => ['high',   '**',   '<b>',    '</b>'],
            'default is empty'             => ['high',   '',     '<b>',    '</b>'],
            'default is not a string'      => ['high',   123,    '<b>',    '</b>'],
        ];
    }

    /**
     * The marker branch returns before the configured default is read, which is what keeps this
     * to API v2: EngineV3 has already reduced the tag to a marker by the time the highlighter
     * calls in, so a v2 element configured here cannot reach a Markdown response.
     */
    public function testTheConfiguredDefaultDoesNotDisturbAMarker(): void
    {
        foreach(Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS as $marker) {
            $this->assertSame([$marker, $marker], Helpers::buildHighlightTags($marker, 'high'), $marker);
        }
    }

    /** The accepted set is the whitelist until an install adds to it. */
    public function testTheElementWhitelistIsTheConstantWithoutAConfiguredDefault(): void
    {
        $this->assertSame(Helpers::HIGHLIGHT_TAG_WHITELIST, Helpers::highlightElementWhitelist());
        $this->assertSame(Helpers::HIGHLIGHT_TAG_WHITELIST, Helpers::highlightElementWhitelist(NULL));
    }

    /**
     * And the addition is exactly one entry, lower-cased - the documented list is built from
     * this, so a duplicate or a stray casing would reach the parameter docs.
     */
    public function testTheConfiguredDefaultIsAppendedToTheElementWhitelistOnce(): void
    {
        $whitelist = Helpers::highlightElementWhitelist('High');

        $this->assertContains('high', $whitelist);
        $this->assertSame(count(Helpers::HIGHLIGHT_TAG_WHITELIST) + 1, count($whitelist));
        $this->assertSame(array_unique($whitelist), $whitelist);
    }

    /** A default already on the whitelist adds nothing. */
    public function testAWhitelistedConfiguredDefaultIsNotDuplicated(): void
    {
        $this->assertSame(Helpers::HIGHLIGHT_TAG_WHITELIST, Helpers::highlightElementWhitelist('EM'));
        $this->assertSame(Helpers::HIGHLIGHT_TAG_WHITELIST, Helpers::highlightElementWhitelist('b'));
    }

    /** And one that is not an element name adds nothing either. */
    public function testAnUnusableConfiguredDefaultIsNotAddedToTheElementWhitelist(): void
    {
        foreach(['<b>', '**', '&&', '%', '', 'my tag', '![x](javascript:alert(1))'] as $default) {
            $this->assertSame(
                Helpers::HIGHLIGHT_TAG_WHITELIST,
                Helpers::highlightElementWhitelist($default),
                var_export($default, TRUE)
            );
        }
    }
}
