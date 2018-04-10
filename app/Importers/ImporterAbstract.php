<?php

namespace App\Importers;

use App\Models\Bible;
use PhpSpec\Exception\Exception;
use \DB;

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
    protected $_existing = FALSE;
    protected $_table = NULL;

    protected $required = ['module', 'lang_short']; // Array of required fields (for specific importer type);

    protected $_insertable = [];
    protected $_insert_threshold = 200;

    // Formats for incoming markup
    protected $italics_st = '[';
    protected $italics_en = ']';
    protected $redletter_st = '<';
    protected $redletter_en = '>';
    protected $strongs_st = '{';
    protected $strongs_en = '}';

    public function __construct() {

    }

    abstract public function import();

    public function setBibleAttributes($att) {
        $this->bible_attributes = $att;
    }

    public function setFile($file) {
        $this->file = $file;
    }

    public function setProperties($file, $module, $overwrite, $attributes) {
        $this->file      = $file;
        $this->module    = $module;
        $this->overwrite = ($overwrite) ? TRUE : FALSE;

        $attributes['module']       = $module;
        $attributes['shortname']    = (!empty($attributes['shortname']))    ? $attributes['shortname'] : $module;
        //$attributes['name']         = (!empty($attributes['name']))         ? $attributes['name'] : $attributes['shortname'];
        $attributes['lang']         = (!empty($attributes['lang']))         ? $attributes['lang'] : NULL;
        $attributes['lang_short']   = (!empty($attributes['lang_short']))   ? $attributes['lang_short'] : NULL;

        foreach($this->required as $item) {
            if(empty($attributes[$item])) {
                $this->addError($item . ' is required', 4);
            }
        }

        $attributes['rank'] = 9999;
        $this->bible_attributes = $attributes;
        return ($this->has_errors) ? FALSE : TRUE;
    }

    protected function _addVerse($book, $chapter, $verse, $text) {
        $book    = intval($book);
        $chapter = intval($chapter);
        $verse   = intval($verse);
        $text    = $this->_formatText($text);
        $text    = $this->_formatItalics($text);
        $text    = $this->_formatStrongs($text);
        $text    = $this->_formatRedLetter($text);

        // SHOULD PARAGRAPH MARKER BE PART OF THE TEXT?
        $text    = $this->_formatParagraph($text);

        /*
         * Items that need to be mapped (for each import type):
         *
         * Paragraph
         * Psalm titles (future?)
         * Pauline postscripts (future?)
         */

        $this->_insertable[] = array(
            'book'             => $book,
            'chapter'          => $chapter,
            'verse'            => $verse,
            'chapter_verse'    => $chapter * 1000 + $verse,
            'text'             => $text,
        );

//        die('bacon');

        if(count($this->_insertable) > $this->_insert_threshold) {
            $this->_insertVerses();
        }
    }

    protected function _insertVerses() {
        DB::table($this->_table)->insert($this->_insertable);
        $this->_insertable = [];
    }

    protected function _formatText($text) {
        return trim($text);
    }

    protected function _formatStrongs($text) {
        $find = [$this->strongs_st, $this->strongs_en];
        $rep  = ['{', '}'];
        $text = $this->_replaceTagsIfNeeded($find, $rep, $text);

        $text = preg_replace_callback('/\{[^\}]+\}/', function($matches) {
//            var_dump($matches[0]);
            $st_numbers = [];
//            die();

            preg_match_all('/[GHgh][0-9]+/', $matches[0], $submatches);
//            var_dump($submatches);

            foreach($submatches as $smatch) {
                $st_numbers[] = '{' . $smatch[0] . '}';
            }

//            var_dump($st_numbers);
            return implode(' ', $st_numbers);

        }, $text);

        return $text;
    }

    protected function _formatItalics($text) {
        return $text;
    }

    protected function _formatRedLetter($text) {
        return $text;
    }

    protected function _formatParagraph($text) {
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
        $this->_existing = ($Bible) ? TRUE   : FALSE;
        $Bible  = ($Bible) ? $Bible : new Bible;
        $Bible->module = $module;
        $Verses = $Bible->verses();
        $this->_table = $Verses->getTable();
        return $Bible;
    }

    protected function _processBibleAttributes($attr) {

    }
}
