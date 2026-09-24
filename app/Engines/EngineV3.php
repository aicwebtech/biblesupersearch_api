<?php

namespace App\Engines;

// We extend the previous engine version. 
use App\Engines\EngineV2 as BaseEngine;
use App\Helpers;

// V3 and newer API engines do NOT return HTML in API responses, instead they return Markdown 
// (or plain text) for all fields that previously returned HTML. 
// This is to avoid XSS vulnerabilities and to make the API more secure and easier to use.

class EngineV3 extends BaseEngine
{
    /** Own singleton slot - see the note on EngineV2. */
    protected static $instance = NULL;

    protected static $api_version = 3;

    /**
     * v3 answers in Markdown, so an HTML element name is not something it can highlight with -
     * '<b>' in a Markdown response is markup the client never asked for. The markers alone.
     *
     * A caller who asks for 'em' is not refused the request: _sanitizeInput() drops the value,
     * the field falls back to the configured default, and _highlightResults() answers with
     * Markdown bold.
     */
    public const HIGHLIGHT_TAG_WHITELIST = Helpers::HIGHLIGHT_PLAIN_TEXT_MARKERS;

    /**
     * Sanitize HTML for API responses by converting it to Markdown
     *
     * Everything reaching this hook has already been through the purifier - either via
     * Engine::_sanitizeHtml() or from a model accessor - so the converter is told not to
     * repeat that work.
     *
     * An absent value is answered with NULL rather than the empty string - see
     * Engine::_sanitizeHtml().
     *
     * @param string|null $html
     * @return string|null
     */
    protected function _processHtml(?string $html): ?string
    {
        if($html === NULL) {
            return NULL;
        }

        return Helpers::convertHtmlToMarkdown($html, FALSE);
    }

    protected function _highlightResults($results, $Search, $Passages, $input) 
    {
        // A missing or misconfigured default would otherwise fall through to the v2 element and
        // put HTML in a Markdown response, so the marker constant - not NULL - is what backs it up.
        $default = config('bss.defaults.highlight_tag_v3');

        if(!Helpers::isPlainTextHighlightMarker($default)) {
            $default = Helpers::DEFAULT_HIGHLIGHT_MARKER;
        }

        $highlight_tag = array_key_exists('highlight_tag', $input) ? $input['highlight_tag'] : $default;

        // An element name has no place in a Markdown response, and neither has a fragment of
        // markup - anything that is not a plain-text marker becomes Markdown bold.
        if(!Helpers::isPlainTextHighlightMarker($highlight_tag)) {
            $highlight_tag = $default;
        }

        if($Search) {
            $results = $Search->highlightResults($results, $highlight_tag);
        }

        if($Passages && count($Passages) == 1 && $input['context']) {
            $results = $Passages[0]->highlightContext($results, $highlight_tag);
        }

        return $results;
    }

    /**
     * Applies the requested markup mode to the verse text.
     *
     * v3 answers in Markdown, so 'raw' is an alias of 'safe' here rather than its own mode.
     * On v2 'raw' hands the column back exactly as stored, and a module imported with
     * --rawtext (ImporterAbstract::$raw_format) stores HTML - which would put HTML in a
     * Markdown response, the same leak HIGHLIGHT_TAG_WHITELIST closes for the highlight tag.
     *
     * The request is not refused: 'raw' stays on MARKUP_MODE_WHITELIST so a v2 client moving
     * to v3 keeps working, and it answers with the most a Markdown response can carry.
     *
     * Which leaves two modes on v3:
     * - 'safe' keeps the Bible SuperSearch markers - {} Strong's, [] added words, ‹› red
     *   letter - and strips HTML out from around them. 'raw' resolves to this.
     * - 'none' removes the markers as well, and is the default.
     *
     * @param array $results
     * @param string $mode One of MARKUP_MODE_WHITELIST
     * @return array
     */
    protected function _processMarkup($results, $mode)
    {
        $mode = strtolower((string) $mode);

        if($mode == 'raw') {
            $mode = 'safe';
        }

        return parent::_processMarkup($results, $mode);
    }

    /**
     * Appends additional data to the statics response.
     *
     * @param \stdClass $response response object to append to
     * @param array $input input parameters from the request
     * @return \stdClass
     */
    protected function staticsAppend(\stdClass $response, array $input): \stdClass
    {
        return $response;
    }
}
