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
            // Off the whitelist because HTMLPurifier has no definition for it either.
            'mark'             => ['mark',   '<b>',      '</b>'],
            'heading'          => ['h1',     '<b>',      '</b>'],
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
}
