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

    /**
     * Close the file when a verse chunk throws. _renderStart() opens the handle and only
     * _renderFinish() closes it, so without this a failed render leaks the handle for the rest
     * of the process - RenderManager carries on to the next Bible - and on Windows leaves the
     * half-written file locked.
     */
    protected function _onVerseRenderError(\Throwable $e) 
    {
        // Unchecked: the render is being abandoned already, and a close failure raised
        // here would replace the exception that actually explains the failure.
        $this->_closeFile(FALSE);

        // The half-written file has to go too. render() writes in place, and the throw
        // skips the bookkeeping that would have updated the Rendering record -- so the
        // *previous* render's rendered_at, version and meta_hash all survive, and
        // isRenderNeeded() would report FALSE and hand this truncated file out as the
        // current render. Removing it forces a re-render instead.
        static::removeCreatedFile($this->getRenderFilePath());
    }

    /**
     * Write to the open render file, failing loudly on a short write.
     *
     * fwrite() reports the number of bytes it actually wrote, which can be fewer than it
     * was given without being FALSE -- a full disk is the usual cause. Ignoring the count
     * produces a silently truncated Bible download that still looks like a clean render,
     * so the byte count is compared rather than just checked for FALSE.
     *
     * @param  string  $text
     * @return void
     * @throws \Exception
     */
    protected function _write($text) 
    {
        $length = strlen($text);

        if($length === 0) {
            return;
        }

        $written = fwrite($this->handle, $text);

        if($written !== $length) {
            $this->_throwWriteFailure($written, $length);
        }
    }

    /**
     * @param  int|false  $written
     * @param  int|null   $expected
     * @return void
     * @throws \Exception
     */
    protected function _throwWriteFailure($written, $expected = NULL) 
    {
        $detail = 'Please contact the administrator.';

        if(config('app.debug')) {
            $detail = 'wrote ' . var_export($written, TRUE)
                . ($expected === NULL ? '' : ' of ' . $expected . ' bytes')
                . ' to ' . $this->getRenderFilePath();
        }

        throw new \Exception('Failed to write render file, ' . $detail);
    }

    protected function _openFile() 
    {
        $filepath = $this->getRenderFilePath(TRUE);

        static::removeStaleFile($filepath);

        $this->handle = fopen($filepath, 'w');
        
        if (!$this->handle) {
            $fd = config('app.debug') ? $filepath:  'Please contact the administrator.';
            throw new \Exception("Failed to open render file, " . $fd);
        }
    }

    /**
     * Close the render file.
     *
     * fclose() flushes whatever is still buffered, so a disk that filled mid-render can
     * surface here rather than at any individual write. That makes the close result part
     * of the "did this render actually succeed" answer, not a formality.
     *
     * @param  bool  $check  FALSE on an error path, where throwing would mask the cause
     * @return void
     * @throws \Exception
     */
    protected function _closeFile($check = TRUE) 
    {
        if (!$this->handle) {
            return;
        }

        $handle = $this->handle;
        $this->handle = null;

        if(!fclose($handle) && $check) {
            // _renderFinish() calls this from outside render()'s inner try/catch, so an
            // exception raised here never reaches _onVerseRenderError() and its cleanup.
            // Without removing the artifact, the previous render's metadata would still
            // make isRenderNeeded() report FALSE and this partial file would be served.
            static::removeCreatedFile($this->getRenderFilePath());

            $detail = config('app.debug') ? $this->getRenderFilePath() : 'Please contact the administrator.';

            throw new \Exception('Failed to close render file, ' . $detail);
        }
    }
}