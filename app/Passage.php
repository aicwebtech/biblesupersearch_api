<?php

namespace App;

use App\Models\Books\BookAbstract as Book;

class Passage {
    
    use Traits\Error;
    
    protected $Book;    // Book instance - Single or Start of range
    protected $Book_En; // Book instance - Range end
    protected $is_book_range = FALSE;
    public $is_search = FALSE;
    protected $raw_reference; // Reference as entered by user
    protected $raw_book;          // Book as entered by user
    protected $raw_chapter_verse; // Chapter and verse as entered by user
    protected $chapter_verse; // Chapter and verse part of reference
    protected $Verses; // Array of Verses instances
    protected $languages; // Array of language short names
    protected $is_valid = FALSE; // Is the provided reference valid?
    
    public function __construct() {
        // Do something?
    }
    
    /**
     * Sets the book (or book range for searches)
     * @param string|int $book
     */
    public function setBook($book) {
        $this->raw_book = $book;
        
        if(strpos($book, '-') !== FALSE) {
            // handle book ranges
            if(!$this->is_search) {
                return $this->_addBookError('Cannot retrieve multiple books at once.');
            }
            
            $books = explode('-', $book);
            $Book_St = $this->findBook( trim($books[0]) );
            $Book_En = $this->findBook( trim($books[1]) );
            
            if($Book_St && $Book_En) {
                $this->is_book_range = TRUE;
                $this->is_valid      = TRUE;
                $this->Book    = $Book_St;
                $this->Book_En = $Book_En;
            }
            else {
                return $this->_addBookError('Invalid book in book range.');
            }
        }
        else {
            $this->is_book_range = FALSE;
            $Book = $this->findBook($book);

            if($Book) {
                $this->Book = $Book;
                $this->is_valid = TRUE;
            }
            else {
                return $this->_addBookError("Book '$book' not found");
            }
        }
    }
    
    /**
     * Logs error with regards to book processing
     * @param string $message
     */
    protected function _addBookError($message) {
        $this->is_valid = FALSE;
        $this->Book = NULL;
        $this->Book_En = NULL;
        $this->is_book_range = FALSE;
        $this->addError($message);
    }
    
    /**
     * Finds the given book, using the tables for the specified languages
     * @param string $book
     * @return Book $Book
     */
    public function findBook($book) {
        if(is_array($this->languages)) {
            foreach($this->languages as $lang) {
                $Book = Book::findByEnteredName($book, $lang);

                if($Book) {
                    $found = TRUE;
                    break;
                }
            }
        }
        else {
            $Book = Book::findByEnteredName($book);
        }
        
        return $Book;
    }
    
    public function setChapterVerse($chapter_verse) {
        //echo(PHP_EOL . $chapter_verse . PHP_EOL);
        $this->raw_chapter_verse = preg_replace('/\s+/', ' ', $chapter_verse);
        $cv_parsed = array();
        $chapter_verse = str_replace([';',' '], [',',''], $chapter_verse);
        $len = strlen($chapter_verse);
        
        for($pos = 0; $pos < $len; $pos ++) {
            $char = $chapter_verse{$pos};
            
        }
        
        //echo(PHP_EOL . $chapter_verse . PHP_EOL);
        $this->chapter_verse = $chapter_verse;
    }
    
    public function __set($name, $value) {
        $settable = ['languages', 'is_search'];
        
        if($name == 'book') {
            $this->setBook($value);
        }
        if($name == 'chapter_verse') {
            echo(PHP_EOL . $chapter_verse . PHP_EOL);
            $this->setChapterVerse($value);
        }
        if(in_array($name, $settable)) {
            $this->$name = $value;
        }
    }
    
    public function __get($name) {
        $gettable = ['languages', 'is_search', 'is_book_range', 'is_valid', 'Book', 'Book_En', 'raw_book', 'raw_reference', 'raw_chapter_verse', 'chapter_verse'];
        
        if(in_array($name, $gettable)) {
            return $this->$name;
        }
    }
    
    /**
     * Parses the reference string from the user into references that can be used by the query
     * @param string $reference
     * @param array $languages - languages to check (short names)
     * @param bool $is_search - whether the parser should interpret this as a search
     * @return array $Passages - array of passage instances
     */
    public static function parseReferences($reference, $languages = array(), $is_search = FALSE) {
        $def_language = env('DEFAULT_LANGUAGE_SHORT', 'en');
        
        if(!in_array($def_language, $languages)) {
            $languages[] = $def_language;
        }
        
        $references = $Passages = array();
        $ref_end  = strlen($reference) - 1;
        $book_end = FALSE;
        
        for($pos = $ref_end; $pos >= 0; $pos --) {
            $char = $reference{$pos};

            if(!$book_end && ctype_alpha($char)) {
                $book_end = $pos;
            }
            elseif($book_end && ($char == ',' || $char == ';' || $pos == 0)) {
                $bpos = ($pos == 0) ? 0 : $pos + 1;
                $book    = trim(substr($reference, $bpos, $book_end - $pos + 1), ' .,;');
                $chapter = trim(substr($reference, $book_end +1, $ref_end - $book_end), ' .,;');                
                
                $Passages[] = self::parseSingleReference($book, $chapter, $languages, $is_search);

                $ref_end = $pos;
                $book_end = FALSE;
            }
        }
        
        $Passages = array_reverse($Passages); // To keep the references in the same order that they were submitted        
        return $Passages;
    }
    
    /**
     * Parses out the string for a single passage reference
     * @param string $book - string reppresenting the book of the passage reference
     * @param string $chapter_verse - string representing the chapter and verse references
     * @return \App\Passage
     */
    public static function parseSingleReference($book, $chapter_verse, $languages = array(), $is_search = FALSE) {
        $Passage = new static;
        $Passage->languages = $languages;
        $Passage->is_search = $is_search;
        $Passage->setBook($book);
        $Passage->setChapterVerse($chapter_verse);        
        return $Passage;
    }
    
}
