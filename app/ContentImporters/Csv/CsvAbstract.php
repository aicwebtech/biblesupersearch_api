<?php

namespace App\ContentImporters\Csv;

use App\ContentImporters\SpreadsheetAbstract;

/**
 * Class CsvAbstract
 *
 * Base for content importers that read CSV files.  Streams the file, so a large one is never
 * held in memory at once.
 */
abstract class CsvAbstract extends SpreadsheetAbstract
{
    protected $separator = ',';
    protected $enclosure = '"';

    /**
     * No escape character: standard CSV doubles a quote inside a field, and a backslash escape
     * would make a field ending in a literal backslash swallow the next record
     */
    protected $escape = '';

    /**
     * @var resource|null
     */
    protected $handle = NULL;

    protected function openFile(string $path): bool
    {
        $handle = is_readable($path) ? @fopen($path, 'rb') : FALSE;

        if(!$handle) {
            return $this->addError('Could not open file', 4);
        }

        $this->handle = $handle;
        return TRUE;
    }

    protected function readBatches(int $size): \Generator
    {
        if(!$this->handle) {
            return;
        }

        rewind($this->handle);

        // Skipped before fgetcsv() reads the first row: a BOM ahead of an enclosure would make
        // fgetcsv() read that enclosure as part of the value
        if(fread($this->handle, 3) !== "\xEF\xBB\xBF") {
            rewind($this->handle);
        }

        $position = 0;
        $batch = [];

        while(($data = fgetcsv($this->handle, NULL, $this->separator, $this->enclosure, $this->escape)) !== FALSE) {
            $position++;

            // A blank line reads as a single NULL field
            if($position < $this->first_row_data || $data === [NULL]) {
                continue;
            }

            $batch[$position] = $data;

            if(count($batch) >= $size) {
                yield $batch;
                $batch = [];
            }
        }

        if($batch) {
            yield $batch;
        }
    }

    protected function closeFile(): void
    {
        if($this->handle) {
            fclose($this->handle);
        }

        $this->handle = NULL;
    }
}
