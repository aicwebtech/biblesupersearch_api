<?php

/**
 * Common code for importing from Excel, OpenDocument and CSV files
 */
namespace App\Importers;
use App\Models\Bible;
use App\Models\Books\En as BookEn;
use App\Rules\NonNumericString;
use \DB; 
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
    protected $file_extensions = []; // Define on child classes
    protected $source = ""; // Where did you get this Bible?

    protected $has_cli = FALSE;
    protected $has_gui = TRUE;
    
    protected $_last_book_name = NULL;
    protected $_last_book_num  = 0;

    protected $column_map = [];
    protected $first_row_data = 1; // modified from user entry, assumes 0-based index

    protected $first_book = 1;
    protected $first_chapter = 1;
    protected $first_verse = 1;

    protected $first_unique_book = 1;
    protected $first_unique_chapter = 2;
    protected $first_unique_verse = 3;
    protected $first_unique_id = 34;

    protected function _importHelper(Bible &$Bible) {
        ini_set("memory_limit", "150M"); // TODO - need to test this with LARGE UNICODE BIBLES to make sure it doesn't break!
            // Confirmed working with thaikjv .xls AND .csv (10+ MB files)

        // $Bible     = $this->_getBible($this->module);
        $file_path = $this->getImportDir() . $this->file;

        if(!$this->overwrite && $this->_existing && $this->insert_into_bible_table) {
            // return $this->addError('Module already exists: \'' . $this->module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if($this->insert_into_bible_table) {
            $attr = $this->bible_attributes;
            
            if($this->source) {
                $attr['description'] .= ($attr['description']) ? '<br /><br />' : '';
                $attr['description'] .= $this->source;
            }

            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);

        if(!$this->_importFromSpreadsheet($file_path)) {
            return FALSE;
        }

        return TRUE;
    }

    abstract protected function _importFromSpreadsheet($file_path);

    protected function _mapSpreadsheetRow($row) {
        $row = array_values($row);
        
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
                    $mapped['text']      = $mapped['text']      ?: $value;
                    break;                
                case 'v':
                    $mapped['verse']     = $mapped['verse']     ?: (int) $value;
                    break;
                case 'c':
                    $mapped['chapter']   = $mapped['chapter']   ?: (int) $value;
                    break;                
                case 'b':
                    $mapped['book']      = $mapped['book']      ?: (int) $value;
                    break;                
                case 'bn':
                    $mapped['book_name'] = $mapped['book_name'] ?: $value;
                    break;                
                case 'c:v':
                    $parts = explode(':', $value);
                    $chapter = array_key_exists(0, $parts) ? $parts[0] : NULL;
                    $verse = array_key_exists(1, $parts) ? $parts[1] : NULL;
                    $mapped['chapter']   = $mapped['chapter']   ?: (int) $chapter;
                    $mapped['verse']     = $mapped['verse']     ?: (int) $verse;
                    break;                  
                case 'bn c:v':
                    $pts1 = explode(' ', $value);
                    $mapped['book_name'] = $mapped['book_name'] ?: $pts1[0];

                    if(array_key_exists(1, $pts1)) {
                        $pts2 = explode(':', $pts1[1]);

                        $chapter = array_key_exists(0, $pts2) ? $pts2[0] : NULL;
                        $verse = array_key_exists(1, $pts2) ? $pts2[1] : NULL;
                        $mapped['chapter']   = $mapped['chapter']   ?: (int) $chapter;
                        $mapped['verse']     = $mapped['verse']     ?: (int) $verse;
                    }

                    break;                
                case 'b c:v':
                    $pts1 = explode(' ', $value);
                    $mapped['book']      = $mapped['book']      ?: (int) $pts1[0];

                    if(array_key_exists(1, $pts1)) {
                        $pts2 = explode(':', $pts1[1]);

                        $chapter = array_key_exists(0, $pts2) ? $pts2[0] : NULL;
                        $verse = array_key_exists(1, $pts2) ? $pts2[1] : NULL;
                        $mapped['chapter']   = $mapped['chapter']   ?: (int) $chapter;
                        $mapped['verse']     = $mapped['verse']     ?: (int) $verse;
                    }

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

        // print_r($this->column_map);
        // print_r($row);
        // print_r($mapped);

        return $mapped;
    }

    protected function _checkParsedFile($rowdata) {
        $required = [
            'book'      => 'Either book number or book name',
            'chapter'   => 'Chapter number',
            'verse'     => 'Verse number',
            'text'      => 'Text',
        ];

        $msg_ext = ' is required but cannot be found with the given column role settings.';

        // Validation rules, sans requirement
        $rules = [
            'book_name' => [new NonNumericString, 'nullable'],
            'book'      => 'integer',
            'chapter'   => 'integer',
            'verse'     => 'integer',
            'text'      => [new NonNumericString],
        ];

        $count = 0;
        $limit = 100; // How many verses to check before bailing

        foreach($rowdata as $key => $row) {
            $count  ++;
            $mapped = $this->_mapSpreadsheetRow($row);
            $ov     = FALSE; // one valid

            foreach($required as $key => $msg) {
                if(empty($mapped[$key])) {
                    $this->addError($msg . $msg_ext, 4);
                }
                else {
                    $ov = TRUE;
                }
            }

            // if($count == 1 && $ov) {
            if($count == 1 && !$this->hasErrors()) {
                if($mapped['book'] != $this->first_book || $mapped['chapter'] != $this->first_chapter || $mapped['verse'] != $this->first_verse) {
                    $Book = BookEn::find($this->first_book);
                    $fv = $Book->name . ' ' . $this->first_chapter . ':' . $this->first_verse;
                    $this->addError('First verse found is not '. $fv . '; please adjust \'First Row of Verse Data\' accordingly.');
                }
            }            
            elseif($count == $this->first_unique_id && !$this->hasErrors()) {
                if($mapped['book'] != $this->first_unique_book || $mapped['chapter'] != $this->first_unique_chapter || $mapped['verse'] != $this->first_unique_verse) {
                    $Book = BookEn::find($this->first_unique_book);
                    $fv = $Book->name . ' ' . $this->first_unique_chapter . ':' . $this->first_unique_verse;
                    $msg = \App\Helpers::ordinal($this->first_unique_id) . ' verse found is not ' . $fv . '; ';

                    if(is_integer($mapped['book']) && is_integer($mapped['chapter']) && is_integer($mapped['book'])) {
                        $this->addError($msg . ' your column selections for book, chapter and verse may be incorrect.  Please adjust them accordingly.');
                    }
                    else {
                        $this->addError($msg . ' please adjust \'First Row of Verse Data\' accordingly.  If it is correct, then this importer may not be able to import this file.');
                    }
                }
            }
            else if(!$ov) {
                $this->resetErrors();
                $this->addError('No valid Bible verse fields found; please make sure that the column roles and \'First Row of Verse Data\' is correct.');
            }
            else if($this->hasErrors()) {
                $this->addError('If the columm roles are correct, please make sure that \'First Row of Verse Data\' is correct.');
            }

            if($this->hasErrors()) {
                return FALSE;
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
        $this->column_map = [];
        ksort($set);
        $col_map_count = 0;

        $required = [
            'book'      => 'Book Name or Number',
            'chapter'   => 'Chapter',
            'verse'     => 'Verse',
            'text'      => 'Text',
        ];

        $found = array_fill_keys(array_keys($required), FALSE);

        foreach($set as $key => $value) {
            if(substr($key, 0, 3) == 'col') {
                $value = ($value && $value != 'null') ? $value : NULL;
                $this->column_map[] = $value ? trim($value) : NULL;
                $col_map_count += ($value) ? 1 : 0;
            }
        }

        foreach($this->column_map as $map) {
             switch($map) {
                case 't':
                    $found['text'] = TRUE;
                    break;                
                case 'v':
                    $found['verse'] = TRUE;
                    break;
                case 'c':
                    $found['chapter'] = TRUE;
                    break;                
                case 'b':
                    $found['book'] = TRUE;
                    break;                
                case 'bn':
                    $found['book'] = TRUE;
                    break;                
                case 'c:v':
                    $found['chapter'] = TRUE;
                    $found['verse'] = TRUE;
                    break;                  
                case 'bn c:v':
                    $found['book'] = TRUE;
                    $found['chapter'] = TRUE;
                    $found['verse'] = TRUE;
                    break;                
                case 'b c:v':
                    $found['book'] = TRUE;
                    $found['chapter'] = TRUE;
                    $found['verse'] = TRUE;
                    break;
                case 'none':
                default:
                    // do nothing
            }
        }

        foreach($found as $key => $f) {
            if(!$f) {
                $this->addError('Please specify a column for ' . $required[$key]);
            }
        }

        if(array_key_exists('first_row_data', $set)) {
            $frd = (int) $set['first_row_data'];

            if($frd < 1) {
                $this->addError('First Row of Verse Data must be a positive integer', 4);
            }

            $this->first_row_data = $frd - 1;
        }
        else {
            $this->addError('First Row of Verse Data is required', 4);
        }

        return $this->hasErrors() ? FALSE : TRUE;
    }
}
