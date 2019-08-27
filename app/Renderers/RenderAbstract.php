<?php

namespace App\Renderers;

use App\Models\Bible;
use DB;

abstract class RenderAbstract {
    use \App\Traits\Error;

    static public $name;
    static public $description;
    protected $file_extension;

    protected $Bible;

    protected $chunk_size = 100;

    protected $include_book_name = FALSE;
    protected $book_name_language_force = NULL;
    protected $book_name_field = 'name';
    protected $include_special = FALSE;  // Include italics / strongs fields (that may not be used anymore)

    public function __construct($module) {
        $this->Bible = Bible::findByModule($module);

        if(!$this->Bible) {
            $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
        }

        if(!$this->file_extension) {
            throw new Exception('$this->file_extension is required on render class!');
        }
    }

    /**
     * Generates the output file and saves it to disk
     * @return boolean
     */
    public function render($overwrite = FALSE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        $file_path = $this->getRenderFilePath();

        if(!$overwrite && is_file($file_path)) {
            return $this->addError('File already exists');
        }

        $success = $this->_renderStart();

        if(!$success) {
            return FALSE;
        }

        $this->_beforeVerseRender();
        $Verses = $this->Bible->verses();
        $table  = $Verses->getTable();
        $Query  = DB::table( $table )->select($table . '.id','book','chapter','verse','text');

        if($this->include_special) {
            $Query->addSelect('italics');
            $Query->addSelect('strongs');
        }

        if($this->include_book_name) {
            $book_table = $this->_getBookTable();
            $Query->join($book_table, $table . '.book', $book_table . '.id');
            $Query->addSelect($book_table . '.' . $this->book_name_field . ' AS book_name');
        }

        $closure = function($rows) {
           foreach($rows as $row) {
               $this->_renderSingleVerse($row);
           }
        };

        $Query->orderBy($table . '.id')->chunk($this->chunk_size, $closure);
        $this->_afterVerseRender();
        $success = $this->_renderFinish();

        return $success;
    }

    /**
     * This initializes the file, and does other pre-rendering work
     * @param bool $overwrite
     */
    protected function _renderStart() {
        return TRUE;
    }

    /**
     *
     */
    abstract protected function _renderSingleVerse($verse);

    /**
     * Does any nessessary tasks after rendering is finished, such as closing a file stream
     *
     * @return bool $success
     */
    protected function _renderFinish() {
        return TRUE;
    }

    /**
     * Code to be executed before individusl verses are rendered
     * Possible Usage: Title page, preface, copyright info
     */
    protected function _beforeVerseRender() { }

    /**
     * Code to be executed after individusl verses are rendered
     * Usage: Finishing pages
     */
    protected function _afterVerseRender() { }

    protected function _getBookTable() {
        if($this->book_name_language_force) {
            return 'books_' . $this->book_name_language_force;
        }

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($this->Bible->lang_short)) {
            return 'books_' . $this->Bible->lang_short;
        }

        $lang = config('bss.defaults.language_short');

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($lang)) {
            return 'books_' . $lang;
        }

        return 'books_en';
    }

    public function output() {

    }

    public function getRenderFilePath($create_dir = FALSE) {
        if($this->hasErrors()) {
            return FALSE;
        }

        $renderer = (new \ReflectionClass($this))->getShortName();
        $module = $this->Bible->module;

        $dir = static::getRenderBasePath() . $renderer;

        if(!is_dir($dir) && $create_dir) {
            mkdir($dir, 0775, TRUE);
        }

        $path = $dir . '/' . $module . '.' . $this->file_extension;
        return $path;
    }

    public static function getRenderBasePath() {
        return dirname(__FILE__) . '/../../bibles/rendered/';
    }
}

