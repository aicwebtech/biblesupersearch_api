<?php

namespace App\Renderers;

class Csv extends RenderAbstract 
{
    static public $name = 'CSV';
    static public $description = 'Comma separated values.  UTF-8 encoding.';

    protected $file_extension = 'csv';
    protected $include_book_name = TRUE;
    protected $text = '';
    protected $handle;
    protected $escape = "\\";  // :todo: this should be a setting or default to ""

    /**
     * This initializes the file, and does other pre-rendering work
     * @param bool $overwrite
     */
    protected function _renderStart() 
    {
        $filepath = $this->getRenderFilePath(TRUE);
        
        if(is_file($filepath)) {
            unlink($filepath);
        }

        $this->handle = fopen($filepath, 'w');
        fputcsv($this->handle, [$this->Bible->name], escape: $this->escape);
        fwrite($this->handle, PHP_EOL . PHP_EOL);
        fwrite($this->handle, '"' . $this->_getCopyrightStatement(TRUE, '  ') . '"');
        fwrite($this->handle, PHP_EOL . PHP_EOL);
        fputcsv($this->handle, ['Verse ID','Book Name', 'Book Number', 'Chapter', 'Verse', 'Text'], escape: $this->escape);
        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {
        fputcsv($this->handle, [$verse->id, $verse->book_name, $verse->book, $verse->chapter, $verse->verse, $verse->text], escape: $this->escape);
    }

    protected function _renderFinish() 
    {
        fclose($this->handle);
        return TRUE;
    }
}
