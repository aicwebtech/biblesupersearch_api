<?php

namespace Tests\Feature\Lang;

use Tests\TestCase;
use App\Helpers;

/**
 * BSS-290: the documented highlight_tag list and the whitelist the API enforces are the same
 * list.
 *
 * A tag off Helpers::HIGHLIGHT_TAG_WHITELIST is dropped in Engine::_sanitizeInput() with no
 * error in the response - the caller is answered with the configured default and nothing says
 * the tag was refused - so the documentation is the only place a caller learns which tags are
 * accepted. resources/lang/en/query.php interpolates the constant for that reason, and this
 * asserts the interpolation still resolves rather than reaching the page as a literal.
 */
class HighlightTagDocumentationTest extends TestCase
{
    /** The rendered parameter description names every accepted element. */
    public function testTheDocumentedTagListMatchesTheWhitelist(): void
    {
        $description = trans('query.params.highlight_tag.description');

        $this->assertNotSame('query.params.highlight_tag.description', $description);

        foreach(Helpers::HIGHLIGHT_TAG_WHITELIST as $tag) {
            $this->assertMatchesRegularExpression('/\b' . preg_quote($tag, '/') . '\b/', $description, $tag);
        }
    }

    /**
     * And says what happens to a tag that is not on it, since the response itself carries no
     * warning.
     */
    public function testTheDescriptionSaysAnUnlistedTagIsIgnored(): void
    {
        $description = trans('query.params.highlight_tag.description');

        $this->assertStringContainsString('ignored', $description);
        $this->assertStringContainsString('default', $description);
    }

    /**
     * And names the Markdown markers, which are all v3 accepts.
     *
     * The joined list, not each marker on its own: '*' is a substring of '**', so per-marker
     * containment would pass on a list that had lost entries, and the word boundaries the
     * element test above relies on do not apply to punctuation.
     */
    public function testTheDocumentedMarkerListMatchesThePlainTextMarkers(): void
    {
        $description = trans('query.params.highlight_tag.description');

        $this->assertStringContainsString(implode(', ', Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS), $description);
    }

    /**
     * v3 answers in Markdown, so EngineV3::HIGHLIGHT_TAG_WHITELIST is the markers alone and an
     * element name off this page is dropped there with no error - the page is the only place a
     * caller can learn that, so the note has to stay on it.
     */
    public function testTheDescriptionSaysV3AcceptsMarkersOnly(): void
    {
        $description = trans('query.params.highlight_tag.description');

        $this->assertStringContainsString('v3', $description);
        $this->assertStringContainsString('only the Markdown markers are accepted', $description);
    }

    /**
     * The two versions fall back to different values - 'b' on v2, '**' on v3 - so the Default
     * column has to name both rather than the v2 element alone.
     */
    public function testTheDocumentedDefaultNamesBothVersionDefaults(): void
    {
        $default = trans('query.params.highlight_tag.default');

        $this->assertStringContainsString(config('bss.defaults.highlight_tag'), $default);
        $this->assertStringContainsString(config('bss.defaults.highlight_tag_v3'), $default);
    }

    /**
     * The configured default has to be a tag buildHighlightTags() will emit, or every request
     * that omits highlight_tag - and every request whose tag was dropped - would fall back to a
     * tag it refuses in turn.
     *
     * Not 'is on HIGHLIGHT_TAG_WHITELIST': an install's own default is accepted off it, which is
     * the point of Helpers::highlightElementWhitelist(). What has to hold is that the default
     * survives the round trip as its own element.
     */
    public function testTheConfiguredDefaultResolvesToItself(): void
    {
        $default = config('bss.defaults.highlight_tag');

        $this->assertSame(
            ['<' . strtolower($default) . '>', '</' . strtolower($default) . '>'],
            Helpers::buildHighlightTags($default, $default)
        );
    }

    /**
     * And the parameter docs name it, whether or not it is on the whitelist - the response
     * carries no warning when a tag is dropped, so this page is the only place a caller can
     * learn what this install accepts.
     */
    public function testTheDocumentedTagListNamesTheConfiguredDefault(): void
    {
        $default = config('bss.defaults.highlight_tag');

        $this->assertContains($default, Helpers::highlightElementWhitelist($default));
        $this->assertMatchesRegularExpression(
            '/\b' . preg_quote($default, '/') . '\b/',
            trans('query.params.highlight_tag.description'),
            $default
        );
    }

    /**
     * Same for v3, against the marker list. A misconfigured DEFAULT_HIGHLIGHT_TAG_V3 sends every
     * v3 request through EngineV3::_highlightResults()'s Helpers::DEFAULT_HIGHLIGHT_MARKER
     * fallback while this page advertises the bad value as the default.
     */
    public function testTheConfiguredV3DefaultIsAPlainTextMarker(): void
    {
        $this->assertContains(config('bss.defaults.highlight_tag_v3'), Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS);
    }
}
