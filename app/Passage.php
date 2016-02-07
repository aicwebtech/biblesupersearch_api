<?php

namespace App;

use App\Models\Books\BookAbstract as Book;
use App\Models\Shortcuts\ShortcutAbstract as Shortcut;

/**
 * Class for parsing passage references
 */

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
    protected $chapter_verse_parsed; // Chapter and verse, parsed into an array of arrays
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
            $book_st = array_shift($books);
            $book_en = array_pop($books);
            $book_en = ($book_en) ? $book_en : $book_st;
            $Book_St = $this->findBook( $book_st );
            $Book_En = $this->findBook( $book_en );
            
            if($Book_St && $Book_En) {
                $this->is_book_range = TRUE;
                $this->is_valid      = TRUE;
                $this->Book    = $Book_St;
                $this->Book_En = $Book_En;
            }
            else {
                return $this->_addBookError("Invalid book in book range: '$book'");
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
    
    public function setBookRange($book_range) {
        $this->raw_book = $book_range;
        $book_range = trim( preg_replace('/\s+/', ' ', $book_range) );
        $books = explode('-', $book_range);
        $book_st = array_shift($books);
        $book_en = array_pop($books);
        $book_en = ($book_en) ? $book_en : $book_st;
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
    
    /**
     * Finds a shortcut using given reference
     * Returns the reference if no shortcut is found.
     * @param string $reference
     * @param array $languages
     * @return object|strins
     */
    static public function findShortcut($reference, $languages, $return_as_string = FALSE) {
        $SC = Shortcut::findByEnteredName($reference);
        
        if(!$SC) {
            return ($return_as_string) ? $reference : FALSE;
        }
        else {
            return ($return_as_string) ? $SC->reference : $SC;
        }
        
        /*
        if(is_array($languages)) {
            foreach($languages as $lang) {
                $SC = Shortcut::findByEnteredName($reference, $lang);

                if($SC) {
                    $found = TRUE;
                    break;
                }
            }
        }
        else {
            $SC = Shortcut::findByEnteredName($reference);
        }
        
        return $SC;
         * 
         */
    }
    
    public function setChapterVerse($chapter_verse) {
        $this->raw_chapter_verse = preg_replace('/\s+/', ' ', $chapter_verse);
        $chapter_verse = str_replace([';',' '], [',',''], $chapter_verse);
        $chapter_verse = preg_replace('/,+/', ',', $chapter_verse);
        $chapter_verse = preg_replace('/-+/', '-', $chapter_verse);
        $chapter_verse = preg_replace('/:+/', ':', $chapter_verse);
        $this->chapter_verse = $chapter_verse;
        
        $preparsed = $matches = $counts = $parsed = array();
        $counts['number'] = preg_match_all('/[0-9]+/', $chapter_verse, $matches['number'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        
        if(!$counts['number']) {
            $this->chapter_verse_parsed = ($this->is_search) ? array() : array( array('c' => 1, 'v' => NULL, 'type' => 'single') );
            return;
        }
        
        $counts['comma']  = preg_match_all('/,/', $chapter_verse, $matches['comma'],  PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $counts['hyphen'] = preg_match_all('/-/', $chapter_verse, $matches['hyphen'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $counts['colon']  = preg_match_all('/:/', $chapter_verse, $matches['colon'],  PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        
        foreach($matches as $k => $ar) {
            foreach($ar as $match) {
                $item = $match[0][0];
                $item = ($k == 'number') ? intval($item) : $item;
                $preparsed[$match[0][1]] = $item;
            }
        }
        
        ksort($preparsed);
        $preparsed_values = array_values($preparsed);
        $count = count($preparsed_values);
        $current_chapter = $current_verse = $cst = $vst = $last_int = NULL;
        $is_range = FALSE;
        
        // Chapters only - if reference contains no verses
        if($counts['colon'] == 0) { 
            foreach($preparsed_values as $in => $value) {
                if(is_int($value)) {
                    $next = (isset($preparsed_values[$in + 1])) ? $preparsed_values[$in + 1] : NULL;
                    
                    if(!$current_chapter && $next == '-') {
                        $current_chapter = $value;
                    }
                    elseif($current_chapter && $next != '-') {
                        $parsed[] = array('cst' => $current_chapter, 'vst' => NULL, 'cen' => $value, 'ven' => NULL, 'type' => 'range');
                        $current_chapter = NULL;
                    }
                    elseif($next == NULL || $next == ',') {
                        $parsed[] = array('c' => $value, 'v' => NULL, 'type' => 'single');
                        $current_chapter = NULL;
                    }
                }
            }
        }
        // Parse out chapter / verse references
        else {
            foreach($preparsed_values as $in => $value) {
                $next = (isset($preparsed_values[$in + 1])) ? $preparsed_values[$in + 1] : NULL;
                $last = (isset($preparsed_values[$in - 1])) ? $preparsed_values[$in - 1] : NULL;
                $end_of_ref = ($next == ',' || $next == NULL) ? TRUE : FALSE;

                if(is_int($value)) {
                    if($next == ':' || !$current_chapter) { 
                        $current_chapter = $value;
                        $current_verse = NULL;
                    }
                    else {
                        $current_verse = $value;
                    }
                    
                    if($end_of_ref) {
                        // Detect and parse out a range
                        if($is_range && $end_of_ref) {
                            $parsed[] = array('cst' => $cst, 'vst' => $vst, 'cen' => $current_chapter, 'ven' => $current_verse, 'type' => 'range');
                            $is_range = FALSE;
                            $cst = $vst = NULL;
                        }
                   
                        // Detect and parse out a single verse reference
                        elseif($current_chapter) {
                            $parsed[] = array('c' => $current_chapter, 'v' => $current_verse, 'type' => 'single');
                        }
                    }
                    
                    $last_int = $value;
                }
                elseif($value == ':') {
                    if(($next == NULL || !is_int($next)) && !$is_range && $next != '-') {
                        $parsed[] = array('c' => $current_chapter, 'v' => NULL, 'type' => 'single');
                    }
                    
                    if($is_range && $end_of_ref) {
                        $parsed[] = array('cst' => $cst, 'vst' => $vst, 'cen' => $current_chapter, 'ven' => $current_verse, 'type' => 'range');
                        $is_range = FALSE;
                        $cst = $vst = NULL;
                    }
                }
                elseif($value == ',') {
                    // do nothing??
                }
                elseif($value == '-') {
                    if(!$is_range && !$end_of_ref) {
                        $is_range = TRUE;
                        $current_chapter = ($current_chapter) ? $current_chapter : $last_int;
                        $cst = $current_chapter;
                        $vst = $current_verse;
                    }
                    
                    if($end_of_ref) {
                        $parsed[] = array('c' => $current_chapter, 'v' => $current_verse, 'type' => 'single');
                    }
                }
                
                $current_verse = ($end_of_ref) ? NULL : $current_verse;
            }
        }

        $this->chapter_verse_parsed = $parsed;
    }
    
    public function getNormalizedReferences() {
        $parsed = $this->chapter_verse_parsed;
        
        foreach($parsed as &$part) {            
            if(isset($part['type']) && $part['type'] == 'range') {
                $part['vst'] = ($part['vst']) ? $part['vst'] : 0;
                $part['ven'] = ($part['ven']) ? $part['ven'] : 999;
            }
        }
        
        return $parsed;
    }
    
    public function __set($name, $value) {
        $settable = ['languages', 'is_search'];
        
        if($name == 'book') {
            $this->setBook($value);
        }
        if($name == 'chapter_verse') {
            $this->setChapterVerse($value);
        }
        if(in_array($name, $settable)) {
            $this->$name = $value;
        }
    }
    
    public function __get($name) {
        if($name == 'chapter_verse_normal') {
            return $this->getNormalizedReferences();
        }
        
        $gettable = ['languages', 'is_search', 'is_book_range', 'is_valid', 'Book', 'Book_En', 'raw_book', 'raw_reference', 'raw_chapter_verse', 
            'chapter_verse', 'chapter_verse_parsed'];
        
        if(in_array($name, $gettable)) {
            return $this->$name;
        }
    }
    
    public function getParsed() {
        return $this->chapter_verse_parsed;
    }
    
    /**
     * Parses the reference string from the user into references that can be used by the query
     * @param string $reference
     * @param array $languages - languages to check (short names)
     * @param bool $is_search - whether the parser should interpret this as a search
     * @return array|bool $Passages - array of passage instances, or FALSE if nothing parsed
     */
    public static function parseReferences($reference, $languages = array(), $is_search = FALSE) {
        if(!is_string($reference)) {
            return FALSE;
        }
        
        $Passages   = array();
        $pre_parsed = static::explodeReferences($reference);
        $def_language = env('DEFAULT_LANGUAGE_SHORT', 'en');
        
        if(!in_array($def_language, $languages)) {
            $languages[] = $def_language;
        }
        
        foreach($pre_parsed as $key => &$ref) {
            $ref = static::findShortcut($ref, $languages, TRUE);
        }
        
        unset($ref);
        $mid_parsed = implode(';', $pre_parsed);
        $parsed = static::explodeReferences($mid_parsed, TRUE);
        
        foreach($parsed as $ref) {
            $Passages[] = self::parseSingleReference($ref['book'], $ref['chapter_verse'], $languages, $is_search);
        }
      
        return (empty($Passages)) ? FALSE : $Passages;
    }
    
    public static function explodeReferences($reference, $separate_book = FALSE) {
        $exploded = array();
        $ref_end  = strlen($reference) - 1;
        $book_end = FALSE;
        
        for($pos = $ref_end; $pos >= 0; $pos --) {
            $char = $reference{$pos};

            if(!$book_end && ctype_alpha($char)) {
                $book_end = $pos;
            }
            elseif($book_end && ($char == ',' || $char == ';' || $pos == 0)) {
                $bpos = ($pos == 0) ? 0 : $pos + 1;
                
                if($separate_book) {                    
                    $book    = trim(substr($reference, $bpos, $book_end - $pos + 1), ' .,;');
                    $chapter = trim(substr($reference, $book_end + 1, $ref_end - $book_end), ' .,;');
                    $exploded[] = array('book' => $book, 'chapter_verse' => $chapter);
                }
                else {
                    $ref = trim(substr($reference, $bpos, $ref_end - $bpos + 1), ' .,;');
                    $exploded[] = $ref;
                }

                $ref_end = $pos;
                $book_end = FALSE;
            }
        }
        
        // To keep the references in the same order that they were submitted  
        $exploded = array_reverse($exploded);
        return $exploded;
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
