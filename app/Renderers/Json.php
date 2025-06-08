<?php

namespace App\Renderers;

class Json extends TextAbstract 
{
    static public $name = 'JSON';
    static public $description = 'JavaScript Object Notation';

    // Maximum number of Bibles to render with the given format before detatched process is required.   Set to TRUE to never require detatched process.
    static protected $render_bibles_limit = TRUE; 

    // All render classes must have this - indicates the version number of the file.  Must be changed if the file is changed, to trigger re-rendering.
    static protected $render_version = 0.1;     

    // Estimated time to render a Bible of the given format, in seconds.
    static protected $render_est_time = 1;  
       
    // Estimated size to render a Bible of the given format, in MB. 
    static protected $render_est_size = 5;      

    protected $file_extension = 'json';
    protected $include_book_name = TRUE;

    protected $data = NULL;

    /**
     * This initializes the file, and does other pre-rendering work
     */
    protected function _renderStart() 
    {
        $this->data = [
            'metadata' => $this->Bible->getMeta(),
            'verses'   => [],
        ];

        $this->data['metadata']['copyright_statement'] = $this->_getCopyrightStatement(TRUE);

        $this->_openFile();
        return TRUE;
    }

    protected function _renderSingleVerse($verse) 
    {
        $this->data['verses'][] = [
            'book_name' => $verse->book_name, // Adds an extra 1 MB per Bible
            'book'      => $verse->book,
            'chapter'   => $verse->chapter,
            'verse'     => $verse->verse,
            'text'      => $verse->text,
        ];

        $this->current_book    = $verse->book;
        $this->current_chapter = $verse->chapter;
    }

    protected function _renderFinish() 
    {
        fwrite($this->handle, json_encode($this->data));
        $this->_closeFile();
        return TRUE;
    }
}
