<?php

/**
 * Common code for importing from Excel, OpenDocument and CSV files
 */

namespace App\Importers;
use App\Models\Bible;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

abstract class SpreadsheetAbstract extends ImporterAbstract {
    // protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '[';
    protected $italics_en   = ']';
    protected $redletter_st = NULL;
    protected $redletter_en = NULL;
    protected $strongs_st   = NULL;
    protected $strongs_en   = NULL;
    protected $paragraph    = '¶ ';
    protected $path_short   = 'misc';
    protected $file_extensions = ['.zip'];
    protected $source = ""; // Where did you get this Bible?


    public function import() {
        ini_set("memory_limit", "50M");

        // Script settings
        $file   = $this->file;   // File name, minus extension
        $module = $this->module; // Module and db name
        $Bible  = $this->_getBible($module);

        if(!$this->overwrite && $this->_existing && $this->insert_into_bible_table) {
            // return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if($this->insert_into_bible_table) {
            // Do something!
        }

        if($this->enable) {
            $Bible->enable();
        }
        
        return TRUE;
    }

    protected function _mapSpreadsheetRow($row) {

    }

    public function checkUploadedFile(UploadedFile $File) {
        $zipfile    = $File->getPathname();
        $file       = static::sanitizeFileName( $File->getClientOriginalName() );



        return TRUE;
    }
}
