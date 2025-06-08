<?php

namespace App\Renderers;

class PlainText extends TextAbstract 
{
    static public $name = 'Plain Text';
    static public $description = 'Simple, plain text format';

    // Maximum number of Bibles to render with the given format before detatched process is required.   Set to TRUE to never require detatched process.
    static protected $render_bibles_limit = TRUE; 

    // All render classes must have this - indicates the version number of the file.  Must be changed if the file is changed, to trigger re-rendering.
    static protected $render_version = 0.1;     

    // Estimated time to render a Bible of the given format, in seconds.
    static protected $render_est_time = 1;  
       
    // Estimated size to render a Bible of the given format, in MB. 
    static protected $render_est_size = 5;      

    protected $file_extension = 'txt';
    protected $include_book_name = TRUE;

    /**
     * This initializes the file, and does other pre-rendering work
     */
    protected function _renderStart() 
    {
        $this->_openFile();
        fwrite($this->handle, $this->Bible->name . PHP_EOL . PHP_EOL);
        fwrite($this->handle, $this->_wordwrap( $this->_getCopyrightStatement(TRUE) ) . PHP_EOL . PHP_EOL . PHP_EOL);
        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {
        if($verse->book != $this->current_book) {
            $line_above = ($this->current_book) ? PHP_EOL . PHP_EOL : '';
            fwrite($this->handle, $line_above . $this->_wordwrap($verse->book_name) . PHP_EOL);
            $this->current_chapter = NULL;
        }

        if($verse->chapter != $this->current_chapter) {
            $ch_param = ($verse->book == 19) ? 'basic.psalm_n' : 'basic.chapter_n';
            $chapter_name = __($ch_param, ['n' => $verse->chapter]);
            fwrite($this->handle, PHP_EOL . $chapter_name . PHP_EOL . PHP_EOL);
        }

        $text = $verse->verse . ' '  . $verse->text . PHP_EOL;
        fwrite($this->handle, $this->_wordwrap($text) );
        $this->current_book    = $verse->book;
        $this->current_chapter = $verse->chapter;
    }

    protected function _wordwrap($text) 
    {
        $wrap_chars = 80;

        if($this->Bible->lang_short == 'th') {
            $wrap_chars = 320;
        }

        return wordwrap($text, $wrap_chars);
    }
}
