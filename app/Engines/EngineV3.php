<?php

namespace App\Engines;

use App\Engine as BaseEngine;
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
     * @param string|null $html
     * @return string
     */
    protected function _processHtml(?string $html): string
    {
        return Helpers::convertHtmlToMarkdown($html, FALSE);
    }

    /**
     * The Markdown converter escapes '[' and ']', which is exactly the markup 'raw' exists to
     * expose - Psalms 23:1 came back as 'The LORD \[is\] my shepherd', so a client scanning
     * for the bracket pair found nothing. The carets and the Strong's braces are not escaped
     * and need no undoing.
     *
     * @param string $text
     * @return string
     */
    protected function _unescapeBibleMarkup(string $text): string
    {
        return str_replace(['\\[', '\\]'], ['[', ']'], $text);
    }

    protected function _highlightResults($results, $Search, $Passages, $input) 
    {
        $highlight_tag = array_key_exists('highlight_tag', $input) ? $input['highlight_tag'] : config('bss.defaults.highlight_tag');

        // Force highlight tag to markdown bold if it's not a valid Markdown tag (alphanumeric only)
        if(preg_match('/^[a-zA-Z0-9]+$/', $highlight_tag)) {
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
