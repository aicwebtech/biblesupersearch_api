<?php

namespace App\Renderers;

class Csv extends TextAbstract 
{
    static public $name = 'CSV';
    static public $description = 'Comma separated values.  UTF-8 encoding.';

    protected $file_extension = 'csv';
    protected $include_book_name = TRUE;
    protected $escape = "\\";  // :todo: this should be a setting or default to ""

    static public $extras_class = Extras\Csv::class;

    /**
     * This initializes the file, and does other pre-rendering work
     * @param bool $overwrite
     */
    protected function _renderStart() 
    {
        $this->_openFile();
        $this->_writeCsvRow([$this->Bible->name]);
        $this->_write(PHP_EOL . PHP_EOL);
        $this->_write('"' . $this->_getCopyrightStatement(TRUE, '  ') . '"');
        $this->_write(PHP_EOL . PHP_EOL);
        $this->_writeCsvRow(['Verse ID','Book Name', 'Book Number', 'Chapter', 'Verse', 'Text']);
        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {
        $this->_writeCsvRow([$verse->id, $verse->book_name, $verse->book, $verse->chapter, $verse->verse, $verse->text]);
    }

    /**
     * Write one CSV row, failing loudly rather than silently dropping it.
     *
     * fputcsv() returns the number of bytes written, or FALSE on failure. It formats the
     * row itself, so there is no expected length to compare against the way _write() can;
     * FALSE is the signal available here. A short write that does not report FALSE is
     * still caught, one row later or by the fclose() check in _closeFile().
     *
     * @param  array  $row
     * @return void
     * @throws \Exception
     */
    protected function _writeCsvRow(array $row) 
    {
        if(fputcsv($this->handle, $row, escape: $this->escape) === FALSE) {
            $this->_throwWriteFailure(FALSE);
        }
    }
}
