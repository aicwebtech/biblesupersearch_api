<?php

namespace App\Importers;

use App\Models\Bible;
use App\Models\Books\BookAbstract as Book;
use App\Models\Language;
use PhpSpec\Exception\Exception;
use \DB;
use Illuminate\Support\Facades\DB AS BDB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Abstract class for importing Bible text from third party sources
 *
 * Importer MUST reformat any markup to match this standardized markup
 * Standard markup:
 *  [,] - italics
 *  {,} - (single) Strongs Number
 *  <,> - red letter
 *  ?? - PARAGRAPH??
 *  «,» - Chapter titles in Psalms
 */
abstract class ImporterAbstract 
{
    use \App\Traits\Error;

    public $test_mode = FALSE;

    protected $bible_attributes = [];
    protected $default_dir;
    protected $debug = false;
    protected $file; // File name (no dir)
    protected $module;
    protected $enable = TRUE; // Whether to enable the Bible for use after it has been imported
    protected $overwrite = FALSE;
    protected $save_bible = TRUE;
    protected $insert_into_bible_table = TRUE; // Whether to insert / update record in Bibles table
    protected $raw_format = false; // If true, most text formatting will be skipped
    protected $_existing = null;
    protected $_table = NULL;
    protected $has_cli = TRUE; // Whether there is a command-line interface access to this importer
    protected $has_gui = FALSE; // Whether there is a user interface access (via the Bible manager) to this importer
    protected $path_short = 'misc';  // Path (inside /bibles) to where import files are located
    protected $has_dedicated_dir = NULL; // Whether or not $path_short is dedicated to this specific importer.  Defaults to TRUE if $path_short is 'misc' and FALSE otherwise
    
    protected $file_extensions = []; // White list of allowable file extensions
    protected $settings = []; // User-selectible settings, specific to each importer
    protected $book_metas = []; // Book info, if able to import book list 
    protected $attribute_map = [];

    protected $required = ['module', 'lang_short']; // Array of required fields (for specific importer type);

    protected $_insertable = [];
    protected $_insert_threshold = 200;

    // Formats for incoming markup
    protected $italics_st   = '[';
    protected $italics_en   = ']';
    protected $redletter_st = '<';
    protected $redletter_en = '>';
    protected $paragraph    = '¶ ';
    protected $strongs_st   = '{';
    protected $strongs_en   = '}';
    protected $unused_tags  = []; // These tags. plus EVERYTHING enclosed by them, will be removed from the text
    protected $paragraph_at_verse_end = FALSE; // Whether the paragraph flag is at the end of the verse (it's usually at the beginning)
    protected $_paragraph_next_verse = FALSE;

    protected $_before_import_bible;
    protected $_on_add_verse;
    protected $_after_import_bible;

    // What do do whith Strongs numbers in parentheses: retain, trim, discard
    protected $strongs_parentheses = 'retain';

    public function __construct() 
    {
        $this->resetBibleAttributes();
        
        if($this->has_dedicated_dir === NULL) {
            $this->has_dedicated_dir = ($this->path_short == 'misc') ? FALSE : TRUE;
        }
    }

    /**
     *   Imports the Bible based on the current set of settings
     *   @return bool $success
     */
    public function import() 
    {
        $Bible = $this->_getBible($this->module);

        if(!$this->overwrite && $this->_existing && $this->insert_into_bible_table) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if(is_callable($this->_before_import_bible)) {
            call_user_func($this->_before_import_bible, $Bible);
        }

        if(!$this->_importHelper($Bible)) {
            return FALSE;
        }

        $this->saveBookList();

        if(is_callable($this->_after_import_bible)) {
            call_user_func($this->_after_import_bible, $Bible);
        }

        if($this->enable) {
            $Bible->enable();
        }

        $Language = Language::findByCode($Bible->lang_short, true);
        $Language->initLanguage();

        return TRUE;
    }

    public function saveBookList()
    {
        return false;  // Disabled for now, this is an experimental / development feature only
        // causing issues for some use cases (ie mismatch languages)

        $Bible = Bible::findByModule($this->module);

        $lang = $Bible ? $Bible->lang_short : null;

        if(!$lang) {
            $lang = isset($this->bible_attributes['lang_short']) ? $this->bible_attributes['lang_short'] : null;
        }

        $book_count = count($this->book_metas);
        $ncount = 0;
        $min = 66;
        $tn = 'books_' . strtolower($lang);

        $book_csv_filename = Book::getCsvFileName($lang);

        if(
            !$lang || $book_count < $min || \App\Importers\Database::importFileExists($book_csv_filename)
        ) {
            return false;
        }

        foreach($this->book_metas as $m) {
            $ncount += (isset($m['name']) && $m['name']) ? 1 : 0;
        }

        if($ncount !== $book_count) {
            return false;
        }

        //BDB::transaction(function() use ($lang) {
            Book::createBookTable($lang);

            $Class = Book::getClassNameByLanguageStrict($lang, true, true);

            if(!$Class) {
                throw new \Exception('Class not found for language ' . $lang);
            }

            $ecount = $Class::count();

            if($ecount) {
                // don't attempt to import if any books already exit for the language.
                return false;
            }

            foreach($this->book_metas as $key => $m) {
                $Book = new $Class;
                $Book->id = $key;
                $Book->name = $m['name'];
                $Book->shortname = $m['shortname'];
                $Book->save();
            }

            if(config('bss.dev_tools')) {
                Book::exportToCsv($lang);
            }
        //});

        return true;
    }

    public function setBeforeImportBible(callable $func)
    {
        $this->_before_import_bible = $func;
    }    

    public function setAfterImportBible(callable $func)
    {
        $this->_after_import_bible = $func;
    }    

    public function setOnAddVerse(callable $func)
    {
        $this->_on_add_verse = $func;
    }

    /**
     *   Helper method that does the actual import work
     *   @return bool $success
     */
    abstract protected function _importHelper(Bible &$Bible): bool;

    /**
     *   Checks the uploaded file to make sure it works with the specific importer.
     *   Also, must parse any and all Bible metadata from the file and map them to Bible model attributes 
     *   @param Illuminate\Http\UploadedFile $File - the file to import
     *   @return bool $success
     */
    abstract public function checkUploadedFile(UploadedFile $File): bool;

    public function getImportDir() 
    {
        return dirname(__FILE__) . '/../../bibles/' . $this->path_short . '/';
    }

    public function acceptUploadedFile(UploadedFile $File) 
    {
        if(!$this->checkUploadedFile($File)) {
            return FALSE;
        }

        if(!$this->test_mode) {        
            try {
                $this->file = static::sanitizeFileName( $File->getClientOriginalName() );
                $dest_path = $this->getImportDir() . $this->file;

                // if(!file_exists($dest_path)) {
                    $npath = $File->storeAs($this->path_short, $this->file, 'bibles');
                // }
            }
            catch(\Exception $e) {
                return $this->addError('Could not save import file: ' . $e->getMessage());
            }
        }

        return TRUE;
    }

    public function mapMetaToAttributes($meta, $preserve_attributes = FALSE, $map = NULL) 
    {
        $map = (!$map || !is_array($map)) ? $this->attribute_map : $map;
        $attr = $old_attr = [];

        if($preserve_attributes) {
            $attr = $old_attr = $this->bible_attributes;
        }

        foreach($map as $key => $meta_key) {
            if(array_key_exists($meta_key, $meta)) {
                $equal_old_value = (array_key_exists($key, $old_attr) && $old_attr[$key] == $meta[$meta_key]);

                if(!$equal_old_value) {

                    switch($key) {
                        case 'description':
                            if($meta[$meta_key] && $this->source) {
                                $attr[$key] = $meta[$meta_key] . '<br /><br />' . $this->source;
                            }
                            else if($this->source) {
                                $attr[$key] = $this->source;
                            }
                            else {
                                $attr[$key] = $meta[$meta_key];
                            }

                            break;
                        case 'lang_short':
                            $attr[$key] = static::getLanguageCode($meta[$meta_key]);
                            break;
                        case 'module':
                            $attr[$key] = static::generateUniqueModuleName($meta[$meta_key]);
                            break;
                        default:
                            $attr[$key] = $meta[$meta_key];
                    }
                }
            }
        }

        $this->bible_attributes = $attr;
    }

    public function setBibleAttributes($att) 
    {
        $this->bible_attributes = $att;
    }   

    public function resetBibleAttributes() 
    {
        $this->bible_attributes = [
            'name'          => NULL,
            'shortname'     => NULL,
            'module'        => NULL,
            'description'   => NULL,
            'year'          => NULL,
        ];
    } 

    public function getBibleAttributes() 
    {
        return $this->bible_attributes;
    }

    public function setFile($file) 
    {
        $this->file = $file;
    }

    /**
     * Sets the user specified settings.  These are specific to each importer and some won't use this.
     * 
     * @param array $settings
     * @param bool $map_to_internal_properties - todo - pull internal properties (file, module, overwrite) from the settings array
     * @return bool TRUE if successful, FALSE if not
     */
    public function setSettings($settings, $map_to_internal_properties = FALSE) 
    {
        $this->settings = $settings;

        return $this->_setSettingsHelper();
    }

    /** 
     * Hook for post-processing and validating custom settings
     * 
     * @return bool TRUE if valid, FALSE if not
     */
    protected function _setSettingsHelper() 
    {
        return TRUE;
    }

    /*
     * Used by the CLI importers
     * Not intended to be used by anything else
     */
    public function setProperties($file, $module, $overwrite, $attributes, $autopopulate) 
    {
        $this->file      = $file;
        $this->module    = $module;
        $this->overwrite = (bool) $overwrite;
        $this->save_bible = (!$overwrite || !$autopopulate);

        if(!($overwrite && $autopopulate)) {
            $attributes['module']       = $module;
            $attributes['shortname']    = (!empty($attributes['shortname']))    ? $attributes['shortname'] : $module;
            //$attributes['name']         = (!empty($attributes['name']))         ? $attributes['name'] : $attributes['shortname'];
            $attributes['lang']         = (!empty($attributes['lang']))         ? $attributes['lang'] : NULL;
            $attributes['lang_short']   = (!empty($attributes['lang_short']))   ? $attributes['lang_short'] : NULL;
            $attributes['rank'] = 9999;
        }

        if(!($overwrite && $autopopulate) && !$this->debug) {
            foreach($this->required as $item) {
                if(empty($attributes[$item])) {
                    $this->addError($item . ' is required', 4);
                }
            }
        }

        $this->bible_attributes = $attributes;
        return ($this->has_errors) ? FALSE : TRUE;
    }

    protected function saveBible() 
    {
        if($this->insert_into_bible_table) {
            $attr = $this->bible_attributes;
            $Bible->fill($attr);
            $Bible->save();
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        $Bible->install(TRUE);
    }

    protected function _addVerse($book, $chapter, $verse, $text, $format_text = FALSE) 
    {
        $book    = intval($book);
        $chapter = intval($chapter);
        $verse   = intval($verse);

        if(!$book || !$chapter || !$verse || empty($text)) {
            return;
        }

        if($format_text) {
            $text = $this->_formatText($text);
        }
        
        if($this->paragraph_at_verse_end && $chapter == 1 && $verse == 1) {
            $this->_paragraph_next_verse = TRUE;
        }
       
        $text    = $this->_formatText($text);

        if(is_callable($this->_on_add_verse)) {
            call_user_func($this->_on_add_verse, $book, $chapter, $verse, $text);
        }

        $this->_insertable[] = array(
            'book'             => $book,
            'chapter'          => $chapter,
            'verse'            => $verse,
            'chapter_verse'    => $chapter * 1000 + $verse,
            'text'             => $text,
        );

        if(count($this->_insertable) > $this->_insert_threshold) {
            $this->_insertVerses();
        }
    }

    protected function _insertVerses() 
    {
        DB::table($this->_table)->insert($this->_insertable);
        $this->_insertable = [];
    }

    /*
     * Items that still need to be mapped (for each import type):
     *
     * Psalm titles (future?)
     * Pauline postscripts (future?)
     */
    protected function _formatText($text) 
    {
        if($this->raw_format) {
            return trim($text);
        }

        $text    = $this->_preFormatText($text);
        $text    = $this->_formatItalics($text);
        $text    = $this->_formatStrongs($text);
        $text    = $this->_formatRedLetter($text);
        $text    = $this->_formatParagraph($text);
        $text    = $this->_removeUnusedTags($text);
        $text    = $this->_postFormatText($text);
        return $text;
    }

    protected function _preFormatText($text) 
    {
        return trim($text);
    }

    protected function _postFormatText($text) 
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = preg_replace('/\s+([?,.!:;])/', '$1', $text);
        // $text = preg_replace('/\s+(\W)/', '$1', $text);
        return $text;
    }

    protected function _formatStrongs($text) 
    {
        if(!$this->strongs_st || !$this->strongs_en) {
            return $text;
        }

        $find = [$this->strongs_st, $this->strongs_en];
        $rep  = ['{', '}'];
        $text = $this->_replaceTagsIfNeeded($find, $rep, $text);

        $parentheses = $this->strongs_parentheses;
        $subpattern  = ($parentheses == 'trim') ? '/[GHgh][0-9]+/' : '/\(?[GHgh][0-9]+\)?/';

        $text = preg_replace_callback('/\{[^\}]+\}/', function($matches) use ($subpattern, $parentheses, $text) {
            $st_numbers = [];

            preg_match_all($subpattern, $matches[0], $submatches);

            foreach($submatches as $smatch) {
                if($parentheses == 'discard' && $smatch[0][0] == '(') {
                    continue;
                }

                if(isset($smatch[0])) {
                    $st_numbers[] = '{' . $smatch[0] . '}';
                }
            }

            return (count($st_numbers)) ? implode(' ', $st_numbers) : $matches[0];
        }, $text);

        return $text;
    }

    protected function _formatItalics($text) 
    {
        $find = [$this->italics_st, $this->italics_en];
        $rep  = ['[', ']'];
        return $this->_replaceTagsIfNeeded($find, $rep, $text);
    }

    protected function _formatRedLetter($text) 
    {
        $find = [$this->redletter_st, $this->redletter_en];
        $rep = ['‹','›'];  // NOT <>!, U+2039, U+203A
        $text = $this->_replaceTagsIfNeeded($find, $rep, $text);

        // This was a failed attempt to mark italicized words as red letter.
        // It resulted, in some instances, the remainder of the verse text being made red.
        // if($find[0] && $find[1]) {
        //     $text = str_replace('› [', ' [', $text);
        //     $text = str_replace('] ‹', '] ', $text);
        // }

        return $text;
    }

    protected function _formatParagraph($text) 
    {
        if($this->paragraph_at_verse_end && $this->paragraph) {
            if($this->_paragraph_next_verse) {
                $text = '¶ ' . $text;
                $this->_paragraph_next_verse = FALSE;
            }
            elseif (strpos($text, $this->paragraph) !== FALSE) {
                $this->_paragraph_next_verse = TRUE;
            }

            return $text;
        }

        if($this->paragraph && $this->paragraph != '¶ ') {
            return str_replace($this->paragraph, '¶ ', $text);
        }

        return $text;
    }

    protected function _removeUnusedTags($text) 
    {
        foreach($this->unused_tags as $tag) {
            $regexp = '/<' . $tag . '>[^>]*>/';
            $text = preg_replace($regexp, '', $text);
        }

        return $text;
    }

    protected function _replaceTagsIfNeeded($src, $dest, $text) {
        if(!$src[0] || !$src[1] || ($src[0] == $dest[0] && $src[1] == $dest[1])) {
            return $text;
        }

        return str_replace($src, $dest, $text);
    }

    protected function _getBible($module) 
    {
        $Bible  = Bible::findByModule($module);
        
        if($this->_existing === null) {
            $this->_existing = (bool) $Bible;
        }

        if(!$Bible) {
            $Bible = new Bible;
            $Bible->module = $module;
            // $Bible->save();
        }

        $Verses = $Bible->verses();
        $this->_table = $Verses->getTable();
        return $Bible;
    }

    protected function _saveBible() {

    }

    protected function _processBibleAttributes($attr) 
    {

    }

    public function __get($name) 
    {
        $gettable = ['required', 'save_bible', 'overwrite', 'module', 'file', 'insert_into_bible_table', 'enable', 'settings', 'has_gui', 'has_cli', 'debug'];

        if(in_array($name, $gettable)) {
            return $this->$name;
        }
    }    

    public function __set($name, $value) 
    {
        $bool = ['required', 'save_bible', 'overwrite', 'insert_into_bible_table', 'enable', 'debug', 'raw_format'];
        $str = ['module', 'file'];

        if(in_array($name, $bool)) {
            $this->$name = (bool) $value;
        }        

        if(in_array($name, $str)) {
            $this->$name = $value;
        }
    }

    protected function _validateTextEncoding($text) 
    {
        $allowed = ['UTF-8'];

        $detected = mb_detect_encoding($text, $allowed, TRUE);

        if(!$detected) {
            return $this->addError('This Bible\'s text encoding is not supported.  Must be in UTF-8 encoding');
        }

        return TRUE;
    }

    public static function generateUniqueModuleName($shortname) 
    {
        $module = trim( strtolower($shortname) );
        $module = preg_replace("/\s+/", ' ', $module);
        $module = str_replace(' ', '_', $module);
        $module = substr($module, 0, 250);
        $Bible  = Bible::findByModule($module);

        if(!$Bible) {
            return $module;
        }

        for($i = 1; $Bible; $i++) {
            $module_suggestion = $module . '_' . $i;
            $Bible = Bible::findByModule($module_suggestion);
        }

        return $module_suggestion;
    }

    public static function sanitizeFileName($file_name) 
    {
        $file_name = trim($file_name);
        $file_name = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $file_name);
        $file_name = mb_ereg_replace("([\.]{2,})", '', $file_name);
        return $file_name;
    }

    public static function getLanguageCode($language) 
    {
        if(!$language) {
            return NULL;
        }

        if(strlen($language) == 2) {
            $match_attr = ['code'];
        }
        elseif(strlen($language) == 3) {
            $match_attr = ['iso_639_2'];
        }
        else {
            $match_attr = ['name', 'iso_name', 'native_name'];
        }

        $Lang = NULL;

        while(!$Lang && $match_attr) {
            $attr = array_shift($match_attr);
            $Lang = Language::where($attr, $language)->first();
        }

        return ($Lang) ? $Lang->code : NULL;
    }
}
