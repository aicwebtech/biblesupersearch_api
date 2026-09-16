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

    /**
     * The Markdown converter escapes '[' and ']', which is exactly the markup 'raw' exists to
     * expose - Psalms 23:1 came back as 'The LORD \[is\] my shepherd', so a client scanning
     * for the bracket pair found nothing. The carets and the Strong's braces are not escaped
     * and need no undoing.
     *
     * The brackets are not the only ones: TextConverter escapes '*', '_', '[', ']' and '\',
     * plus a leading '#', and ParagraphConverter escapes a leading '>', '-', '+' or '~', a
     * '<' opening a comment, and the '.' or ')' after a leading number. All of them are
     * undone here, because a backslash in the output can only have come from that escaping -
     * a backslash in the module's own text is escaped to '\\' on the way through.
     *
     * Scanning left to right and not overlapping, so a module's literal '\[' arrives as
     * '\\\[', loses the escape of the backslash first and the escape of the bracket second.
     *
     * @param string $text
     * @return string
     */
    protected function _unescapeBibleMarkup(string $text): string
    {
        return preg_replace('/\\\\([*_\\[\\]\\\\#>\\-+~<.)])/u', '$1', $text);
    }

    protected function _highlightResults($results, $Search, $Passages, $input) 
    {
        $highlight_tag = array_key_exists('highlight_tag', $input) ? $input['highlight_tag'] : config('bss.defaults.highlight_tag');

        // An element name has no place in a Markdown response, and neither has a fragment of
        // markup - anything that is not a plain-text marker becomes Markdown bold.
        if(!Helpers::isPlainTextHighlightMarker($highlight_tag)) {
            $highlight_tag = '**'; // Markdown bold
        }

        if($Search) {
            $results = $Search->highlightResults($results, $highlight_tag);
        }

        if($Passages && count($Passages) == 1 && $input['context']) {
            $results = $Passages[0]->highlightContext($results, $highlight_tag);
        }

        return $results;
    }

    
}
