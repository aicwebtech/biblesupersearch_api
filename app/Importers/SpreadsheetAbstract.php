<?php

/**
 * Common code for importing from Excel, OpenDocument and CSV files
 */
namespace App\Importers;
use App\Models\Bible;
use App\Models\Books\En as BookEn;
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
    protected $file_extensions = []; // Define on child classes
    protected $source = ""; // Where did you get this Bible?
    
    protected $_last_book_name = NULL;
    protected $_last_book_num  = 0;

    protected $column_map = [];
    protected $first_row_data = 1; // modified from user entry, assumes 0-based index

    protected $first_book = 1;
    protected $first_chapter = 1;
    protected $first_verse = 1;

    public function import() {
        ini_set("memory_limit", "150M"); // TODO - need to test this with LARGE UNICODE BIBLES to make sure it doesn't break!
            // Confirmed working with thaikjv .xls AND .csv (10 + MB file)

        $Bible     = $this->_getBible($this->module);
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

        if($this->enable) {
            $Bible->enable();
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
                    $mapped['chapter']   = $mapped['chapter']   ?: (int) $parts[0];
                    $mapped['verse']     = $mapped['verse']     ?: (int) $parts[1];
                    break;                  
                case 'bn c:v':
                    $pts1 = explode(' ', $value);
                    $pts2 = explode(':', $pts1[1]);

                    $mapped['book_name'] = $mapped['book_name'] ?: $pts1[0];
                    $mapped['chapter']   = $mapped['chapter']   ?: (int) $pts2[0];
                    $mapped['verse']     = $mapped['verse']     ?: (int) $pts2[1];
                    break;                
                case 'b c:v':
                    $pts1 = explode(' ', $value);
                    $pts2 = explode(':', $pts1[1]);

                    $mapped['book']      = $mapped['book']      ?: (int) $pts1[0];
                    $mapped['chapter']   = $mapped['chapter']   ?: (int) $pts2[0];
                    $mapped['verse']     = $mapped['verse']     ?: (int) $pts2[1];
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
            // if($key < $this->first_row_data) {
            //     continue;
            // }

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
            else if(!$ov) {
                $this->resetErrors();
                $this->addError('No valid Bible verse fields found; please make sure that \'First Row of Verse Data\' is correct.');
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

        foreach($set as $key => $value) {
            if(substr($key, 0, 3) == 'col') {
                $value = ($value && $value != 'null') ? $value : NULL;
                $this->column_map[] = $value;
                $col_map_count += ($value) ? 1 : 0;
            }
        }

        if($col_map_count < 2) {
            return $this->addError('Column roles are missing or incomplete');
        }

        if(array_key_exists('first_row_data', $set)) {
            $frd = (int) $set['first_row_data'];

            if($frd < 1) {
                return $this->addError('First Row of Verse Data must be a positive integer', 4);
            }

            $this->first_row_data = $frd - 1;
        }

        return TRUE;
    }
}
