<?php

namespace App\ContentImporters;

/**
 * Class SpreadsheetAbstract
 *
 * Base for content importers that read spreadsheet files, ie Excel and CSV.
 *
 * Handles the column mapping the user specifies in the UI.  Settings are the same shape as the
 * Bible spreadsheet importer's, so the UI reuses its column roles component:
 *  first_row_data - 1-based row of the first content row
 *  col_A, col_B ... - the content field each column maps to, from the content class's
 *                     getImportFields(TRUE), or 'none' / empty to ignore the column
 */
abstract class SpreadsheetAbstract extends ContentImportAbstract
{
    /**
     * Mapped columns: 0-based column index => content field
     *
     * @var array<int, string>
     */
    protected $column_map = [];

    /**
     * 1-based row of the first content row
     *
     * @var int
     */
    protected $first_row_data = 2;

    protected function setSettingsHelper(): bool
    {
        $content_class = $this->getContentClass();
        $fields = $content_class::getImportFields(TRUE);
        $this->column_map = [];

        foreach($this->settings as $key => $value) {
            if(!is_string($key) || !preg_match('/^col_([a-z]+)$/i', $key, $matches)) {
                continue;
            }

            $value = is_string($value) ? trim($value) : $value;

            if($value === NULL || $value === '' || $value === 'none' || $value === 'null') {
                continue;
            }

            $column = static::columnToNumber($matches[1]);

            if(!is_string($value) || !array_key_exists($value, $fields)) {
                $this->addError('Column ' . strtoupper($matches[1]) . ': unknown field \'' . (is_scalar($value) ? $value : gettype($value)) . '\'', 4);
                continue;
            }

            $this->column_map[$column - 1] = $value;
        }

        ksort($this->column_map);

        foreach($content_class::IMPORT_REQUIRED_FIELDS as $field) {
            if(!$content_class::importFieldIsMapped($field, array_values($this->column_map))) {
                $this->addError('Please specify a column for ' . $this->describeRequiredField($field), 4);
            }
        }

        $first_row = $this->settings['first_row_data'] ?? NULL;

        if($first_row === NULL || $first_row === '') {
            $this->addError('First Row of Data is required', 4);
        }
        elseif(!preg_match('/^[0-9]+$/', (string) $first_row) || (int) $first_row < 1) {
            $this->addError('First Row of Data must be a positive integer', 4);
        }
        else {
            $this->first_row_data = (int) $first_row;
        }

        return !$this->hasErrors();
    }

    /**
     * A required field's label, with any alternative ways of supplying it
     *
     * @param string $field
     * @return string
     */
    protected function describeRequiredField(string $field): string
    {
        $content_class = $this->getContentClass();
        $labels = $content_class::getImportFields(TRUE);
        $text = $labels[$field] ?? $field;

        foreach($content_class::IMPORT_FIELD_ALTERNATIVES[$field] ?? [] as $set) {
            $text .= ', or for each of: ' . implode('; ', array_map(fn($alt) => $labels[$alt] ?? $alt, $set));
        }

        return $text;
    }

    /**
     * @return array<int, string>
     */
    public function getColumnMap(): array
    {
        return $this->column_map;
    }

    public function getFirstRowData(): int
    {
        return $this->first_row_data;
    }

    /**
     * Number of columns that need reading: through the last mapped one
     *
     * @return int
     */
    protected function getColumnCount(): int
    {
        return $this->column_map ? max(array_keys($this->column_map)) + 1 : 1;
    }

    /**
     * The first non-empty value mapped to a field wins.  A cell cellError() refuses makes the
     * row invalid (see addRowError()) and is skipped.
     */
    protected function mapRow(array $raw, int|string $row): array
    {
        $raw = array_values($raw);
        $mapped = [];

        foreach($this->column_map as $index => $field) {
            $value = $raw[$index] ?? NULL;

            if($value === NULL) {
                continue;
            }

            $value = trim((string) $value);

            $error = $this->cellError($index, $row);

            if($error !== NULL) {
                $this->addRowError('Row ' . $row . ', column ' . static::numberToColumn($index + 1) . ' ' . $error);
                continue;
            }

            if(!isset($mapped[$field]) || $mapped[$field] === '') {
                $mapped[$field] = $value;
            }
        }

        return $mapped;
    }

    /**
     * Why a cell cannot be imported, or NULL when it can.  Every cell can by default: a CSV
     * holds plain text, so even one starting with '=' is content.
     *
     * @param int $index 0-based column
     * @param int|string $row Row as passed to mapRow()
     * @return string|null Reason, completing "Row n, column X ..."
     */
    protected function cellError(int $index, int|string $row): ?string
    {
        return NULL;
    }

    /**
     * Column letters to its 1-based number: A => 1, Z => 26, AA => 27
     *
     * @param string $column
     * @return int
     */
    public static function columnToNumber(string $column): int
    {
        $number = 0;

        foreach(str_split(strtoupper($column)) as $char) {
            $number = $number * 26 + (ord($char) - 64);
        }

        return $number;
    }

    /**
     * 1-based column number to its letters: 1 => A, 26 => Z, 27 => AA
     *
     * @param int $number
     * @return string|null NULL for a number below 1
     */
    public static function numberToColumn(int $number): ?string
    {
        if($number < 1) {
            return NULL;
        }

        $column = '';

        while($number > 0) {
            $mod = ($number - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $number = intdiv($number - $mod - 1, 26);
        }

        return $column;
    }
}
