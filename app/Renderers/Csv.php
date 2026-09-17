<?php

namespace App\Renderers;

class Csv extends TextAbstract 
{
    use \App\Traits\WritesFilesSafely;

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
     * Write one CSV row, failing loudly rather than silently dropping or truncating it.
     *
     * The row is formatted first and then written through _write(), which compares the
     * byte count. Handing it straight to fputcsv() and testing for FALSE is not enough:
     * fputcsv() reports a refused write as 0 and a short write as the count it managed,
     * so the disk-full case never produces FALSE at all.
     *
     * @param  array  $row
     * @return void
     * @throws \Exception
     */
    protected function _writeCsvRow(array $row) 
    {
        $this->_write(static::csvRowToString($row, $this->escape));
    }
}
