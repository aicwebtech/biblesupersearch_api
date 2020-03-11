<?php

namespace aicwebtech\BibleSuperSearch\Renderers;

class MachineReadableText extends RenderAbstract {
    static public $name = 'Machine-readable Plain Text';
    static public $description = '';

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


    protected $text = '';

    protected $handle;

    /**
     * This initializes the file, and does other pre-rendering work
     */
    protected function _renderStart() {
        $filepath = $this->getRenderFilePath(TRUE);
        $this->handle = fopen($filepath, 'w');
        fwrite($this->handle, $this->Bible->name . PHP_EOL . PHP_EOL);
        fwrite($this->handle, $this->_getCopyrightStatement(TRUE) . PHP_EOL . PHP_EOL . PHP_EOL);
        return TRUE;
    }

    protected function _renderSingleVerse($verse) {
        $text = $verse->book_name . ' ' . $verse->chapter . ':' . $verse->verse . ' '  . $verse->text . PHP_EOL;
        fwrite($this->handle, $text);
    }

    protected function _renderFinish() {
        fclose($this->handle);
        return TRUE;
    }


}
