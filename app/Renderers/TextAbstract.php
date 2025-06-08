<?php 

namespace App\Renderers;

/**
 * Abstract class for text-based renderers.
 * This class provides the basic structure for rendering text files, such as plain text or CSV.
 */
abstract class TextAbstract extends RenderAbstract
{
    protected $file_extension = 'txt';
    protected $include_book_name = TRUE;

    protected $text = '';
    protected $handle;
    protected $chunk_size = 1000;

    /**
     * This initializes the file, and does other pre-rendering work
     */
    protected function _renderStart() 
    {
        $this->_openFile();
        return TRUE;
    }

    protected function _renderFinish() 
    {
        $this->_closeFile();
        return TRUE;
    }

    protected function _openFile() 
    {
        $filepath = $this->getRenderFilePath(TRUE);

        if(is_file($filepath)) {
            unlink($filepath);
        }

        $this->handle = fopen($filepath, 'w');
        
        if (!$this->handle) {
            $fd = config('app.debug') ? $filepath:  'Please contact the administrator.';
            throw new \Exception("Failed to open render file, " . $fd);
        }
    }

    protected function _closeFile() 
    {
        if ($this->handle) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
}