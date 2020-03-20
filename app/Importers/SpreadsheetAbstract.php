<?php

/**
 * Common code for importing from Excel, OpenDocument and CSV files
 */

namespace App\Importers;
use App\Models\Bible;
use App\Rules\NonNumericString;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;
use Validator;

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
    protected $_last_book_name = NULL;
    protected $_last_book_num  = 0;

    protected $column_map = [];


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
        $row = array_values($row);

        // print_r($row);
        
        $mapped = [
            'book_name' => NULL,
            'book'      => NULL,
            'chapter'   => NULL,
            'verse'     => NULL,
            'text'      => NULL,
        ];

        foreach($this->column_map as $key => $map) {
            if(!$map || !array_key_exists($key, $row)) {
                continue;
            }

            $value = $row[$key];
            $value = trim($value);
            $value = preg_replace('/\s+/', ' ', $value);

            switch($map) {
                case 't':
                    $mapped['text'] = $value;
                    break;                
                case 'v':
                    $mapped['verse'] = (int) $value;
                    break;
                case 'c':
                    $mapped['chapter'] = (int) $value;
                    break;                
                case 'b':
                    $mapped['book'] = (int) $value;
                    break;                
                case 'bn':
                    $mapped['book_name'] = $value;
                    break;                
                case 'c:v':
                    $parts = explode(':', $value);
                    $mapped['chapter']   = $parts[0];
                    $mapped['verse']     = $parts[1];
                    break;                  
                case 'bn c:v':
                    $pts1 = explode(' ', $value);
                    $pts2 = explode(':', $pts1[1]);

                    $mapped['book_name'] = $pts1[0];
                    $mapped['chapter']   = $pts2[0];
                    $mapped['verse']     = $pts2[1];
                    break;                
                case 'b c:v':
                    $pts1 = explode(' ', $value);
                    $pts2 = explode(':', $pts1[1]);

                    $mapped['book']      = $pts1[0];
                    $mapped['chapter']   = $pts2[0];
                    $mapped['verse']     = $pts2[1];
                    break;
                case 'none':
                default:
                    // do nothing
            }
        }

        if(!$mapped['book'] && $mapped['book_name']) {
            if($mapped['book_name'] != $this->_last_book_name) {
                $this->_last_book_name  = $mapped['book_name'];
                $this->_last_book_num   ++;
            }

            $mapped['book'] = $this->_last_book_num;
        }

        print_r($this->column_map);
        print_r($row);
        print_r($mapped);

        return $mapped;
    }

    public function checkUploadedFile(UploadedFile $File) {
        $zipfile    = $File->getPathname();
        $file       = static::sanitizeFileName( $File->getClientOriginalName() );



        return TRUE;
    }

    protected function _checkParsedFile($rowdata) {
        $required = [
            'book'      => 'Either book number or book name',
            'chapter'   => 'Chapter number',
            'verse'     => 'Verse number',
            'text'      => 'text',
        ];

        $msg_ext = ' is required but cannot be found with the given column settings.';

        // Validation rules, sans requirement
        $rules = [
            'book_name' => [new NonNumericString, 'nullable'],
            'book'      => 'integer',
            'chapter'   => 'integer',
            'verse'     => 'integer',
            'text'      => [new NonNumericString],
        ];

        $count = 0;
        $limit = 100;

        foreach($rowdata as $key => $row) {
            if($key < $this->settings['first_row_data']) {
                continue;
            }

            $count ++;

            $mapped = $this->_mapSpreadsheetRow($row);

            // print_r($mapped);

            foreach($required as $key => $msg) {
                if(empty($mapped[$key])) {
                    $this->addError($msg . $msg_ext, 4);
                }
            }

            $validator = Validator::make($mapped, $rules);

            if($validator->fails()) {
                $this->addErrors( $validator->errors()->all() );
            }

            if($this->hasErrors() || $count > $limit) {
                break;
            }

        }

        return $this->hasErrors() ? FALSE : TRUE;
    }

    /** 
     * Hook for post-processing and validating custom settings
     * 
     * @return bool TRUE if valid, FALSE if not
     */
    protected function _setSettingsHelper() {
        $set = $this->settings;
        $cols = [];

        ksort($set);

        foreach($set as $key => $value) {
            if(substr($key, 0, 3) == 'col') {
                $value = $value ?: NULL;
                $cols[] = $value;
            }
        }


        $this->column_map = $cols;

        $this->settings['first_row_data'] = (array_key_exists('first_row_data', $set)) ? (int) $set['first_row_data'] -1 : 0;

        $this->settings['first_row_data'] = 6;

        return TRUE;
    }
}
