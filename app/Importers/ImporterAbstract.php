<?php

namespace App\Importers;

use App\Models\Bible;
use PhpSpec\Exception\Exception;
use \DB;
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
abstract class ImporterAbstract {
    use \App\Traits\Error;

    protected $bible_attributes = array();
    protected $default_dir;
    protected $file;
    protected $module;
    protected $overwrite = FALSE;
    protected $save_bible = TRUE;
    protected $_existing = FALSE;
    protected $_table = NULL;
    protected $path_short = 'misc';  // Path (inside /bibles) to where import files are located
    protected $file_extensions = []; // White list of allowable file extensions

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
    protected $unused_tags  = [];

    // What do do whith Strongs numbers in parentheses: retain, trim, discard
    protected $strongs_parentheses = 'retain';

    public function __construct() {

    }

    abstract public function import();

    /**
     *   Checks the uploaded file to make sure it works with the specific importer.
     *   Also, must parse any and all Bible metadata from the file and map them to Bible model attributes 
     *   @param Illuminate\Http\UploadedFile $File - the file to import
     *   @return bool $success
     */
    abstract public function checkUploadedFile(UploadedFile $File);

    public function getImportDir() {
        return dirname(__FILE__) . '/../../bibles/' . $this->path_short . '/';
    }

    public function acceptUploadedFile(UploadedFile $File) {
        if(!$this->checkUploadedFile($File)) {
            return FALSE;
        }

        try {
            $file_name = trim( $File->getClientOriginalName() );
            $file_name = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $file_name);
            $file_name = mb_ereg_replace("([\.]{2,})", '', $file_name);
            $dest_path = $this->getImportDir() . $file_name;

            // if(!file_exists($dest_path)) {
                $npath = $File->storeAs($this->path_short, $file_name, 'bibles');
            // }
        }
        catch(\Exception $e) {
            return $this->addError('Could not save import file: ' . $e->getMessage());
        }

        return TRUE;
    }

    public function setBibleAttributes($att) {
        $this->bible_attributes = $att;
    }    

    public function getBibleAttributes() {
        return $this->bible_attributes;
    }

    public function setFile($file) {
        $this->file = $file;
    }

    public function setProperties($file, $module, $overwrite, $attributes, $autopopulate) {
        $this->file      = $file;
        $this->module    = $module;
        $this->overwrite = ($overwrite) ? TRUE : FALSE;
        $this->save_bible = (!$overwrite || !$autopopulate) ? TRUE : FALSE;

        if(!($overwrite && $autopopulate)) {
            $attributes['module']       = $module;
            $attributes['shortname']    = (!empty($attributes['shortname']))    ? $attributes['shortname'] : $module;
            //$attributes['name']         = (!empty($attributes['name']))         ? $attributes['name'] : $attributes['shortname'];
            $attributes['lang']         = (!empty($attributes['lang']))         ? $attributes['lang'] : NULL;
            $attributes['lang_short']   = (!empty($attributes['lang_short']))   ? $attributes['lang_short'] : NULL;
            $attributes['rank'] = 9999;
        }

        if(!($overwrite && $autopopulate)) {
            foreach($this->required as $item) {
                if(empty($attributes[$item])) {
                    $this->addError($item . ' is required', 4);
                }
            }
        }

        $this->bible_attributes = $attributes;
        return ($this->has_errors) ? FALSE : TRUE;
    }

    protected function _addVerse($book, $chapter, $verse, $text) {
        $book    = intval($book);
        $chapter = intval($chapter);
        $verse   = intval($verse);
        $text    = $this->_formatText($text);

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

    protected function _insertVerses() {
        DB::table($this->_table)->insert($this->_insertable);
        $this->_insertable = [];
    }

    /*
     * Items that still need to be mapped (for each import type):
     *
     * Psalm titles (future?)
     * Pauline postscripts (future?)
     */
    protected function _formatText($text) {
        $text    = $this->_preFormatText($text);
        $text    = $this->_formatItalics($text);
        $text    = $this->_formatStrongs($text);
        $text    = $this->_formatRedLetter($text);
        $text    = $this->_formatParagraph($text);
        $text    = $this->_removeUnusedTags($text);
        $text    = $this->_postFormatText($text);
        return $text;
    }

    protected function _preFormatText($text) {
        return trim($text);
    }

    protected function _postFormatText($text) {
        return preg_replace('/\s+/', ' ', $text);
    }

    protected function _formatStrongs($text) {
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
                if($parentheses == 'discard' && $smatch[0]{0} == '(') {
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

    protected function _formatItalics($text) {
        $find = [$this->italics_st, $this->italics_en];
        $rep  = ['[', ']'];
        return $this->_replaceTagsIfNeeded($find, $rep, $text);
    }

    protected function _formatRedLetter($text) {
        $find = [$this->redletter_st, $this->redletter_en];
        // $rep  = ['<', '>'];
        $rep = ['‹','›'];  // NOT <>!, U+2039, U+203A
        $text = $this->_replaceTagsIfNeeded($find, $rep, $text);

        if($find[0] && $find[1]) {
            $text = str_replace('› [', ' [', $text);
            $text = str_replace('] ‹', '] ', $text);
        }

        return $text;
    }

    protected function _formatParagraph($text) {
        if($this->paragraph && $this->paragraph != '¶ ') {
            return str_replace($this->paragraph, '¶ ', $text);
        }

        return $text;
    }

    protected function _removeUnusedTags($text) {
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

    protected function _getBible($module) {
        $Bible  = Bible::findByModule($module);
        $this->_existing = ($Bible) ? TRUE : FALSE;
        $Bible  = ($Bible) ? $Bible : new Bible;
        $Bible->module = $module;
        $Verses = $Bible->verses();
        $this->_table = $Verses->getTable();
        return $Bible;
    }

    protected function _processBibleAttributes($attr) {

    }

    public function __get($name) {
        $gettable = ['required'];

        if(in_array($name, $gettable)) {
            return $this->$name;
        }
    }

    public static function generateUniqueModuleName($shortname) {
        $module = trim( strtolower($shortname) );
        $module = preg_replace("/\s+/", ' ', $module);
        $module = str_replace(' ', '_', $module);
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
}
