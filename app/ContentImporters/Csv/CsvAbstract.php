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
    protected $escape = '\\';

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

        $position = 0;
        $batch = [];

        while(($data = fgetcsv($this->handle, NULL, $this->separator, $this->enclosure, $this->escape)) !== FALSE) {
            $position++;

            if($position == 1 && isset($data[0])) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]);
            }

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
