<?php

namespace App\Renderers;

use App\Models\Bible;

abstract class RenderAbstract {
    use Traits\Error;

    static public $name;
    static public $description;

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
    }

    /**
     * Generates the output file and saves it to disk
     * @return boolean
     */
    public function render() {
        if($this->hasErrors()) {
            return FALSE;
        }

        $Verses = $this->Bible->verses();
        $table  = $Verses->getTable();
        $Query  = DB::table($table . ' AS tb')->select(DB::raw('tb.id'),'book','chapter','verse','text');

        if($this->include_special) {
            $Query->addSelect('italics');
            $Query->addSelect('strongs');
        }

        if($this->include_book_name) {
            $book_table = $this->_getBookTable();
            $Bibles->join($book_table, DB::raw('tb.book'), $book_table . '.id');
            $Query->addSelect($book_table . '.' . $this->book_name_field . ' AS book_name');
        }

        $closure = function($rows) {
           foreach($rows as $row) {
               $this->_renderSingleVerse($row);
           }
        };

        $Query->orderBy(DB::raw('tb.id'))->chunk($this->chunk_size, $closure);

        return TRUE;
    }

    abstract protected function _renderSingleVerse($verse);

    protected function _getBookTable() {
        if($this->book_name_language_force) {
            return 'bible_books_' . $this->book_name_language_force;
        }

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($this->Bible->lang_short)) {
            return 'bible_books_' . $this->Bible->lang_short;
        }

        $lang = config('bss.defaults.language_short');

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($lang)) {
            return 'bible_books_' . $lang;
        }

        return 'bible_books_en';
    }

    abstract public function output();

}

