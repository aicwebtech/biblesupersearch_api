<?php

namespace App\Renderers;

class Csv extends RenderAbstract {
    static public $name = 'CSV';
    static public $description = 'Comma separated values.';

    protected $file_extension = 'csv';
    protected $include_book_name = TRUE;


    protected $text = '';

    protected $handle;

    /**
     * This initializes the file, and does other pre-rendering work
     * @param bool $overwrite
     */
    protected function _renderStart() {
        $filepath = $this->getRenderFilePath(TRUE);
        $this->handle = fopen($filepath, 'w');
        fputcsv($this->handle, [$this->Bible->name]);
        fwrite($this->handle, PHP_EOL . PHP_EOL);
        fwrite($this->handle, $this->_getCopyrightStatement(TRUE));
        fwrite($this->handle, PHP_EOL . PHP_EOL);
        fputcsv($this->handle, ['Verse ID','Book Name', 'Book Number', 'Chapter', 'Verse', 'Text']);
        return TRUE;
    }

    protected function _renderSingleVerse($verse) {
        fputcsv($this->handle, [$verse->id, $verse->book_name, $verse->book, $verse->chapter, $verse->verse, $verse->text]);
    }

    protected function _renderFinish() {
        fclose($this->handle);
        return TRUE;
    }
}
