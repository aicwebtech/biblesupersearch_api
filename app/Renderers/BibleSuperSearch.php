<?php

namespace App\Renderers;

use App\Models\Bible;
use App\Models\Rendering;
use DB;
use App;

class BibleSuperSearch extends RenderAbstract 
{
    static public $name = 'Bible SuperSearch';
    static public $description = 'Official Bible SuperSearch format.';

    // Maximum number of Bibles to render with the given format before detatched process is required.   Set to TRUE to never require detatched process.
    static protected $render_bibles_limit = 3; 

    // All render classes must have this - indicates the version number of the file.  Must be changed if the file is changed, to trigger re-rendering.
    static protected $render_version = 0.1;    

    // Estimated time to render a Bible of the given format, in seconds.
    static protected $render_est_time = 60;    

    // Estimated size to render a Bible of the given format, in MB.
    static protected $render_est_size = 6;     

    // Extras rendering class, if any.  Must be child of App\Renderers\Extras\ExtrasAbstract
    static public $extras_class = NULL; 

    static public $load_fonts = false;

    protected $file_extension = 'zip';

    protected $include_book_name = FALSE;
    protected $book_name_language_force = NULL;
    protected $book_name_field = 'name';
    protected $include_special = FALSE;  // Include italics / strongs fields (that may not be used anymore)

    public function render($overwrite = FALSE, $suppress_overwrite_error = FALSE)
    {
        return false; // currently unable to render if BSS module file does not already exist
    }

    protected function _verseRender() 
    {
        $file_path = $this->getRenderFilePath();
        return $this->Bible->export($this->overwrite, null);
    }

    /**
     * If for any reason the  given format cannot be rendered using the given Bible
     * This will add an error messge and return false
     * (Note: we already check if the given Bible is able to be rendered into any format)
     */ 
    public function canRenderAndDownload()
    {
        return $this->Bible->hasModuleFile();
    }

    public function isRenderNeeded($ignore_cache = FALSE) 
    {
        return false; // This doesn't actually render

        if($this->Bible->hasModuleFile()) {
            return false;
        }

        return parent::isRenderNeeded($ignore_cache);
    }

    public function deleteRenderFile() 
    {
        if($this->Bible->hasModuleFile()) {
            return;
        }

        parent::deleteRenderFile();
    }

    public function getDownloadFilePath()
    {
        if($this->Bible->hasModuleFile()) {
            return $this->Bible->getModuleFilePath();
        }

        return null; //

        return $this->getRenderFilePath();
    }

}

