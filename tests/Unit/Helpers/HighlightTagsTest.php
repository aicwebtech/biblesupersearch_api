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
     * A tag that already carries its own angle brackets is not an element name, so it is left
     * alone rather than being wrapped a second time into '<<b>>'.
     */
    public function testAnAngleBracketedTagIsNotWrappedAgain(): void
    {
        list($pre, $post) = Helpers::buildHighlightTags('<b>');

        $this->assertStringNotContainsString('<<', $pre);
        $this->assertStringNotContainsString('</<', $post);
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
