<?php

namespace App\Traits\ContentFormatters;

use App\Helpers;

/**
 * Cleans up a mapped Strong's dictionary row before it is validated and stored.
 *
 * Shared by every Strong's importer (CSV, Excel ...), applied after mapping.  An importer can
 * override any single step.
 */
trait StrongsFormatter
{
    /**
     * Plain text columns: stored as UTF-8 text, not HTML
     *
     * @var array<int, string>
     */
    protected $strongs_plain_fields = ['root_word', 'transliteration', 'pronunciation'];

    /**
     * HTML columns
     *
     * @var array<int, string>
     */
    protected $strongs_html_fields = ['definition', 'short_definition'];

    /**
     * @param array<string, mixed> $row Mapped row
     * @return array<string, mixed>
     */
    protected function formatRow(array $row): array
    {
        $row['number'] = $this->formatNumber($row['number'] ?? NULL, $row['number_hg'] ?? NULL, $row['number_int'] ?? NULL);
        unset($row['number_hg'], $row['number_int']);

        foreach($this->strongs_plain_fields as $field) {
            if(array_key_exists($field, $row)) {
                $row[$field] = $this->formatPlainText($row[$field]);
            }
        }

        foreach($this->strongs_html_fields as $field) {
            if(array_key_exists($field, $row)) {
                $row[$field] = $this->formatDefinition($row[$field]);
            }
        }

        return $row;
    }

    /**
     * The Strong's number in stored form (H1, G25, H1234a), from a full number or from its
     * prefix and integer.  A value that cannot be normalized is returned trimmed, as given, for
     * validation to report.
     *
     * @param mixed $number Full number, ie 'H0001', 'h1', 'G 25'
     * @param mixed $prefix H or G, when the number is split across columns
     * @param mixed $integer Integer portion, when the number is split across columns
     * @return string|null
     */
    protected function formatNumber(mixed $number, mixed $prefix = NULL, mixed $integer = NULL): ?string
    {
        $number = trim((string) $number);

        if($number === '') {
            $number = trim((string) $prefix) . trim((string) $integer);
        }

        if($number === '') {
            return NULL;
        }

        if(preg_match('/^([HG])\s*0*([1-9][0-9]*)\s*([A-Z]?)$/i', $number, $matches)) {
            return strtoupper($matches[1]) . $matches[2] . strtolower($matches[3]);
        }

        return $number;
    }

    /**
     * Decodes entities, drops any markup and trims
     *
     * @param mixed $text
     * @return string|null NULL when empty
     */
    protected function formatPlainText(mixed $text): ?string
    {
        if($text === NULL) {
            return NULL;
        }

        $text = trim(strip_tags(html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return ($text === '') ? NULL : $text;
    }

    /**
     * Sanitizes a definition to the HTML the API emits.  Line breaks in a definition without
     * any markup are kept as <br>, as a spreadsheet cell's line breaks are meant to show.
     *
     * @param mixed $text
     * @return string|null NULL when empty
     */
    protected function formatDefinition(mixed $text): ?string
    {
        if($text === NULL) {
            return NULL;
        }

        $text = trim((string) $text);

        if($text === '') {
            return NULL;
        }

        if(!str_contains($text, '<')) {
            $text = preg_replace('/\r\n|\r|\n/', '<br>', $text);
        }

        $text = Helpers::sanitizeHtml($text);

        return ($text === NULL || trim($text) === '') ? NULL : trim($text);
    }
}
