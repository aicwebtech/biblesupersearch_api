<?php

namespace App;

use App\Models\Books\BookAbstract as Book;
use App\Models\Shortcuts\ShortcutAbstract as Shortcut;
use App\Models\Bible;
use App\Helpers;

/**
 * Class for parsing and handling of Bible passage references
 */

class Passage {

    use Traits\Error;

    static public $allow_book_range_without_search = false;

    // REGEXP pattern to match any passage reference
    // This should match ALL valid references.  However, it will match some invalid ones, too
    // Attempted to make unicode safe but not working ...
    // Todo - make unicode safe, attempt to filter out bad references 
    const PASSAGE_REGEXP = '/(([0-9]\s*)?[\p{Lu}\p{M}][\p{L}\p{M}]+(\.|[\p{L}\p{M} ]{0,30})?)\s*([1-9][0-9]*(\s*[:\-,]\s*[1-9][0-9]*(\s*[\-,\s]\s*[1-9][0-9]*([0-9:,\-\s]+[1-9][0-9]*)?)?)?)/';

    public $is_search = FALSE;
    protected $Book;                        // Book instance - Single or Start of range
    protected $Book_En;                     // Book instance - Range end
    protected $is_book_range = FALSE;
    protected $raw_reference;               // Reference as entered by user
    protected $raw_book;                    // Book as entered by user
    protected $raw_chapter_verse;           // Chapter and verse as entered by user
    protected $chapter_verse;               // Chapter and verse part of reference
    protected $chapter_verse_parsed;        // Chapter and verse, parsed into an array of arrays
    protected $chapter_max;                 // Maximum chapter number requested
    protected $chapter_min;                 // Minimum chapter number requested
    protected $verses;                      // Array of verses, grouped by bible, book, chapter, verse as found by the query
    protected $verses_index;                // Array of book / chapter / verse
    protected $verses_count = 0;            // Count of the verses matched to this passage (as found by the query).
    protected $languages;                   // Array of language short names
    protected $Bibles = [];            // Array of Bibles
    protected $is_valid = FALSE;            // Is the provided reference valid?
    protected $is_random = FALSE;           // Is the user requesting a random chapter or verse?
    protected $is_contextual = FALSE;
    protected $contextual_range = 5;
    protected $partial_verses = false;      // Whether the passage actually only contains a subset of the verses indicated by it's reference (mainly for passage-search combinations)

    public function __construct() {
        // Do something?
    }

    public function setBookById($book_id) 
    {
        $language = (is_array($this->languages) && count($this->languages)) ? $this->languages[0] : config('bss.defaults.language_short');
        $book_class = Book::getClassNameByLanguage($language);
        $Book = $book_class::find($book_id);

        if($Book) {
            $this->Book = $Book;
            $this->is_valid = TRUE;
            $this->is_book_range = FALSE;
        }
        else {
            return $this->_addBookError(trans('errors.book.not_found', ['book' => $book_id]));
        }
    }

    /**
     * Sets the book (or book range for searches)
     * @param string|int $book
     */
    public function setBook($book) {
        $this->raw_book = $book;
        $this->is_random = FALSE;

        if(static::isRandom($book)) {
            $this->_generateRandomReference($book);
            return;
        }

        if(strpos($book, '-') !== FALSE) {
            // handle book ranges
            if(!$this->is_search && !static::$allow_book_range_without_search) {
                return $this->_addBookError(trans('errors.book.multiple_without_search'));
            }

            $books = explode('-', $book);
            $book_st = array_shift($books);
            $book_en = array_pop($books);
            $book_en = ($book_en) ? $book_en : $book_st;
            $Book_St = $this->findBook( $book_st, true );
            $Book_En = $this->findBook( $book_en, true );

            if($Book_St && $Book_En) {
                $this->is_book_range = TRUE;
                $this->is_valid      = TRUE;
                $this->Book    = $Book_St;
                $this->Book_En = $Book_En;
                $this->clearChapterVerse();
            }
            else {
                return $this->_addBookError(trans('errors.book.invalid_in_range', ['range' => $book]));
            }
        }
        else {
            $this->is_book_range = FALSE;
            $Book = $this->findBook($book, true);

            if($Book) {
                $this->Book = $Book;
                $this->is_valid = TRUE;
                $this->clearChapterVerse();
            }
            else {
                return $this->_addBookError(trans('errors.book.not_found', ['book' => $book]));
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

    protected function _generateRandomReference($reference) {
        $random = static::normalizeRandom($reference);
        $random = substr($random, 7);

        $Bible = (is_array($this->Bibles) && !empty($this->Bibles)) ? array_values($this->Bibles)[0] : NULL;

        if(!$Bible) {
            return $this->addError('Programming Error: No Bibles Present on Reference', 5);
        }

        $rand = $Bible->getRandomReference($random);

        if(!$rand) {

        }

        // print_r($rand);

        $cb_raw = $this->raw_chapter_verse;
        $this->setBookById($rand['book_id']);
        $this->setChapterVerse($rand['chapter_verse']);
        // $this->raw_book = $this->Book->name;
        // $this->raw_chapter_verse = $rand['chapter_verse'];
        
        $this->raw_chapter_verse = $cb_raw;
        $this->is_random = true;
    }

    /**
     * Finds the given book, using the tables for the specified languages
     * @param string $book
     * @return Book $Book
     */
    public function findBook($book, $loose = false) {
        return static::findBookByNameAndLanguage($book, $this->languages, false, $loose);
    }

    public static function findBookByNameAndLanguage($book, $languages = [], $multiple = FALSE, $loose = false) {
        $found = FALSE;
        $primary_language = NULL;

        if(is_array($languages)) {
            foreach($languages as $key => $lang) {
                if($key == 0) {
                    $primary_language = $lang;
                }

                $Book = Book::findByEnteredName($book, $lang, $multiple, $loose);

                if($Book) {
                    $found = TRUE;
                    break;
                }
            }
        }

        if(!$found) {
            $Book = Book::findByEnteredName($book, NULL, $multiple, $loose);
        }

        if(!$multiple && $Book && $Book->getLanguage() != $primary_language && $primary_language != config('bss.defaults.language_short')) {
            $book_class = Book::getClassNameByLanguage($primary_language);
            $Book = $book_class::find($Book->id);
        }

        if($multiple && $Book && $Book[0]->getLanguage() != $primary_language && $primary_language != config('bss.defaults.language_short')) {
            $book_class = Book::getClassNameByLanguage($primary_language);
            $BooksNew = [];

            foreach($Book as $B) {
                $BooksNew[] = $book_class::find($B->id);
            }

            return $BooksNew;
        }

        if(!$multiple) {
            // die($Book->name);
        }

        return $Book;
    }

    /**
     * Finds a shortcut using given reference
     * Returns the reference if no shortcut is found.
     * @param string $reference
     * @param array $languages
     * @return object|string
     */
    static public function findShortcut($reference, $languages, $return_as_string = FALSE) {
        $SC = Shortcut::findByEnteredName($reference);

        if(!$SC) {
            return ($return_as_string) ? $reference : FALSE;
        }
        else {
            return ($return_as_string) ? $SC->reference : $SC;
        }

        // Retain - for future use
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

    public function setChapterVerse($chapter_verse) 
    {
        if($this->is_random) {
            return;
        }

        $this->clearChapterVerse();
        $chapter_verse = $chapter_verse ?: '';
        $this->raw_chapter_verse = preg_replace('/\s+/', ' ', $chapter_verse);
        $chapter_verse = str_replace([';',' '], [',',''], $chapter_verse);
        $chapter_verse = preg_replace('/,+/', ',', $chapter_verse); // Replace repeated , with one ,
        $chapter_verse = preg_replace('/-+/', '-', $chapter_verse); // Replace repeated - with one -
        $chapter_verse = preg_replace('/:+/', ':', $chapter_verse); // Replace repeated : with one :
        $chapter_verse = (!$this->is_search && !$this->is_book_range && empty($chapter_verse)) ? '1' : $chapter_verse;
        $this->chapter_verse = $chapter_verse;

        $preparsed = $matches = $counts = $parsed = $chapters = [];
        $counts['number'] = preg_match_all('/[0-9]+/', $chapter_verse, $matches['number'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        if(!$counts['number']) {
            $this->chapter_verse_parsed = ($this->is_search || $this->is_book_range) ? [] : array( array('c' => 1, 'v' => NULL, 'type' => 'single') );
            return;
        }

        $counts['comma']  = preg_match_all('/,/', $chapter_verse, $matches['comma'],  PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $counts['hyphen'] = preg_match_all('/-/', $chapter_verse, $matches['hyphen'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        $counts['colon']  = preg_match_all('/:/', $chapter_verse, $matches['colon'],  PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach($matches as $k => $ar) {
            foreach($ar as $match) {
                $item = $match[0][0];
                $item = ($k == 'number') ? (int) $item : $item;
                $preparsed[$match[0][1]] = $item;
            }
        }

        ksort($preparsed);
        $preparsed_values = array_values($preparsed);
        $count = count($preparsed_values);
        $current_chapter = $current_verse = $cst = $vst = $last_int = NULL;
        $is_range = $has_verse = FALSE;

        // Parse out chapter and verse information from the reference.
        // Brute-force method

        // Chapters only - if reference contains no verses
        if($counts['colon'] == 0) {
            foreach($preparsed_values as $in => $value) {
                $next = (isset($preparsed_values[$in + 1])) ? $preparsed_values[$in + 1] : NULL;
                $last = (isset($preparsed_values[$in - 1])) ? $preparsed_values[$in - 1] : NULL;

                if(is_int($value)) {
                    if(!$current_chapter && $next == '-') {
                        $current_chapter = $value;
                    }
                    elseif($current_chapter && $next != '-') {
                        $parsed[] = array('cst' => $current_chapter, 'vst' => NULL, 'cen' => $value, 'ven' => NULL, 'type' => 'range');
                        $chapters[] = $current_chapter;
                        $chapters[] = $value;
                        $current_chapter = NULL;
                    }
                    elseif(($next == NULL || $next == ',') && $last == '-') {
                        $parsed[] = array('cst' => NULL, 'vst' => NULL, 'cen' => $value, 'ven' => NULL, 'type' => 'range');
                        $chapters[] = $value;
                        $current_chapter = NULL;
                    }
                    elseif(($next == NULL || $next == ',') && $last != '-') {
                        $parsed[] = array('c' => $value, 'v' => NULL, 'type' => 'single');
                        $chapters[] = $value;
                        $current_chapter = NULL;
                    }
                }
                else if($value == '-' && $next === NULL) {
                    $parsed[] = array('cst' => $current_chapter, 'vst' => NULL, 'cen' => NULL, 'ven' => NULL, 'type' => 'range');
                    $chapters[] = $current_chapter;
                    $current_chapter = NULL;
                }
            }
        }
        // Parse out chapter / verse references
        else {
            foreach($preparsed_values as $in => $value) {
                $next = (isset($preparsed_values[$in + 1])) ? $preparsed_values[$in + 1] : NULL;
                $last = (isset($preparsed_values[$in - 1])) ? $preparsed_values[$in - 1] : NULL;
                $end_of_ref = ($next == ',' || $next == NULL);

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
                            // Detect if the 'end verse' is actually a chapter.  (When it's less than the start verse and greater than start chapter)
                            $cen = ($current_verse < $vst && $current_verse > $cst && $current_chapter == $cst) ? $current_verse : $current_chapter;
                            $ven = ($current_verse < $vst && $current_verse > $cst && $current_chapter == $cst) ? NULL : $current_verse;

                            //$parsed[] = array('cst' => $cst, 'vst' => $vst, 'cen' => $current_chapter, 'ven' => $current_verse, 'type' => 'range');
                            $parsed[] = array('cst' => $cst, 'vst' => $vst, 'cen' => $cen, 'ven' => $ven, 'type' => 'range');
                            $chapters[] = $cst;
                            $chapters[] = $cen;
                            //$chapters[] = $current_chapter;
                            $is_range = FALSE;
                            $cst = $vst = NULL;
                        }

                        // Detect and parse out a single verse reference
                        elseif($current_chapter) {
                            $parsed[] = $this->_singleVerseHelper($current_chapter, $current_verse);
                            $chapters[] = $current_chapter;
                        }
                    }

                    $last_int = $value;
                }
                elseif($value == ':') {
                    if(($next == NULL || !is_int($next)) && !$is_range && $next != '-') {
                        $parsed[] = array('c' => $current_chapter, 'v' => NULL, 'type' => 'single');
                        $chapters[] = $current_chapter;
                    }

                    if($is_range && $end_of_ref) {
                        if($last == '-') {
                            $parsed[] = array('cst' => $cst, 'vst' => $vst, 'cen' => NULL, 'ven' => NULL, 'type' => 'range');
                            $chapters[] = $cst;
                        }
                        else {
                            $parsed[] = array('cst' => $cst, 'vst' => $vst, 'cen' => $current_chapter, 'ven' => $current_verse, 'type' => 'range');
                            $chapters[] = $cst;
                            $chapters[] = $current_chapter;
                        }
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
                        // If we have a verse with this reference range, the end chapter is the same as the start
                        // Otherwise, we have an indefinite chapter range, and will pull everything to end of book.
                        $cen = ($current_verse || $last == ':') ? $current_chapter : NULL;

                        // Needed for (indefinite) chapter ranges when listed along with single verses: Ex: Genesis 3:4,14:-
                        if($last == ':') {
                            $parsed[] = array('c' => $current_chapter, 'v' => NULL, 'type' => 'single');
                        }
                        else {
                            $parsed[] = array('cst' => $current_chapter, 'vst' => $current_verse, 'cen' => $cen, 'ven' => NULL, 'type' => 'range');
                        }

                        //$parsed[] = array('c' => $current_chapter, 'v' => $current_verse, 'type' => 'single');
                        $chapters[] = $current_chapter;
                    }
                }

                $current_verse = ($end_of_ref) ? NULL : $current_verse;
            }
        }

        $this->chapter_max = (is_array($chapters) && count($chapters)) ? max($chapters) : NULL;
        $this->chapter_min = (is_array($chapters) && count($chapters)) ? min($chapters) : 1;
        $this->chapter_verse_parsed = $parsed;
    }

    protected function _singleVerseHelper($chapter, $verse) {
        if($this->is_contextual && $verse) {
            $item = array(
                'cst' => $chapter,
                'vst' => $verse - $this->contextual_range,
                'cen' => $chapter,
                'ven' => $verse + $this->contextual_range,
                'type' => 'range'
            );
        }
        else {
            $item = array('c' => $chapter, 'v' => $verse, 'type' => 'single');
        }

        return $item;
    }

    public function setChapterVerseFromParsed($parsed_item) {
        $this->clearChapterVerse();
        $chapter_verse = '';
        $chapters = [];

        if($parsed_item['type'] == 'range') {
            if($parsed_item['cst']) {
                $chapters[] = $parsed_item['cst'];
                $chapter_verse .= ($parsed_item['cst']);
                $chapter_verse .= ($parsed_item['vst']) ? ':' . $parsed_item['vst'] : '';
            }

            if($parsed_item['cen']) {
                $chapters[] = $parsed_item['cen'];
            }

            if($parsed_item['cen'] && $parsed_item['cst'] != $parsed_item['cen']) {
                $chapter_verse .= '-';
                $chapter_verse .= ($parsed_item['cen']) ? $parsed_item['cen'] : '';
                $chapter_verse .= ($parsed_item['ven']) ? ':' : '';
            }
            else if($parsed_item['ven'] && !$parsed_item['vst']) {
                $chapter_verse .= ':-';
            }
            elseif($parsed_item['cst'] == $parsed_item['cen'] && $parsed_item['vst']) {
                $chapter_verse .= '-';
            }

            $chapter_verse .= ($parsed_item['ven']) ? $parsed_item['ven'] : '';
        }
        elseif($parsed_item['type'] == 'single') {
            if($parsed_item['c']) {
                $chapters[] = $parsed_item['c'];
            }

            $chapter_verse .= ($parsed_item['c']) ? $parsed_item['c'] : '';
            $chapter_verse .= ($parsed_item['v']) ? ':' . $parsed_item['v'] : '';
        }

        $this->chapter_max = (is_array($chapters) && count($chapters)) ? max($chapters) : NULL;
        $this->chapter_min = (is_array($chapters) && count($chapters)) ? min($chapters) : 1;
        $this->chapter_verse = $chapter_verse;
        $this->chapter_verse_parsed = array($parsed_item);
    }

    public function clearChapterVerse() {
        $this->chapter_verse_parsed = [];
        $this->raw_chapter_verse = NULL;
        $this->chapter_verse = NULL;
        $this->chapter_max = NULL;
        $this->chapter_min = NULL;
        $this->verses = [];
        $this->verses_count = 0;
    }

    public function getNormalizedReferences() {
        $parsed = $this->chapter_verse_parsed;

        if(!is_array($parsed)) {
            return [];
        }

        foreach($parsed as &$part) {
            if(isset($part['type']) && $part['type'] == 'range') {
                $part['vst'] = ($part['vst']) ? $part['vst'] : 0;
                $part['ven'] = ($part['ven']) ? $part['ven'] : 999;
                $part['cst'] = ($part['cst']) ? $part['cst'] : 1;
                $part['cen'] = ($part['cen']) ? $part['cen'] : 999;
            }
        }
        unset($part);

        return $parsed;
    }

    public function getAdjustedChapterVerse() 
    {
        //return $this->chapter_verse;

        $adjusted = $pre = [];
        $chapter_only = $this->isChapterOnly();

        if(!is_array($this->verses)) {
            return FALSE;
        }

        foreach($this->verses as $bible => $chapters) {
            foreach($chapters as $chapter => $verses) {
                foreach($verses as $verse => $content) {
                    $pre[$chapter][$verse] = 1;
                }
            }
        }

        foreach($pre as $chapter => $verses) {
            $v = [];
            $last_verse = $first_verse = NULL;

            if($chapter_only) {
                $adjusted[] = $chapter;
            }
            else {
                foreach($verses as $verse => $s) {
                    $first_verse = ($first_verse) ? $first_verse : $verse;

                    // Causing issues when Bible missing a verse
                    // Commented out, and all unit tests pass so just leaving it commented out
                    // if($last_verse && $verse != $last_verse + 1) {
                    //     $v[] = ($first_verse == $last_verse) ? $first_verse : $first_verse . ' - ' . $verse;
                    //     $first_verse = $verse;
                    // }

                    $last_verse = $verse;
                }

                $v[] = ($first_verse == $last_verse) ? $first_verse : $first_verse . ' - ' . $verse;
                $adjusted[] = $chapter . ':' . implode(',', $v);
            }
        }

        return implode('; ', $adjusted);
    }

    public function highlightContext($results, $highlight_tag = null) 
    {
        if(!$this->is_contextual || !str_contains($this->chapter_verse, ':')) {
            return $results;
        }

        $highlight_tag = $highlight_tag ?: config('bss.defaults.highlight_tag');

        $pre_tag  = '<'  . $highlight_tag . '>';
        $post_tag = '</' . $highlight_tag . '>';

        $parsed = $this->chapter_verse_parsed;
        $b = $this->Book->id;

        list($c, $v) = explode(':', $this->chapter_verse);

        $c = (int) trim($c);
        $v = (int) trim($v);

        foreach($results as $bible => &$verses) {
            foreach($verses as &$verse) {

                if($verse->book == $this->Book->id && $verse->chapter == $c && $verse->verse == $v) {
                    $verse->text = $pre_tag . $verse->text . $post_tag;
                }
            }
            unset($verse);
        }
        unset($verses);

        return $results;
    }

    public function __set($name, $value) {
        $settable = ['languages', 'is_search', 'Bibles', 'is_contextual', 'contextual_range', 'partial_verses'];

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
            'chapter_verse', 'chapter_verse_parsed', 'chapter_max', 'chapter_min'];

        if(in_array($name, $gettable)) {
            return $this->$name;
        }
    }

    public function getParsed() {
        return $this->chapter_verse_parsed;
    }

    public function toArray($verbose = FALSE) {
        $single = $this->containsSingleVerse();

        $passage = array(
            'book_id'           => $this->Book->id,
            'book_name'         => $this->Book->name,
            'book_short'        => $this->Book->shortname,
            'book_raw'          => $this->raw_book,
            'chapter_verse'     => $this->getAdjustedChapterVerse(),
            //'chapter_verse'     => $this->chapter_verse,
            'chapter_verse_raw' => $this->raw_chapter_verse,
            'verse_index'       => $this->generateVerseIndex(),
            'verses'            => $this->verses,
            'verses_count'      => $this->verses_count,
            //'single_verse'      => $this->isSingleVerse(),
            'single_verse'      => $single,
            'nav'               => ($single) ? [] : $this->getNavigationData($verbose),
            //'chapter_verse_parsed' => $this->chapter_verse_parsed, // Debugging only
        );

        if($this->is_book_range) {
            $passage['book_name']  .= ' - ' . $this->Book_En->name;
            $passage['book_short'] .= ' - ' . $this->Book_En->shortname;
        }

        return $passage;
    }

    public function getNavigationData($verbose = FALSE) {
        $nav = [];
        $language = $this->getPrimaryLanguage();
        $book_class = Book::getClassNameByLanguage($language);
        $book_com = config('bss.books_common.' . $this->Book->id);

        $prev_book_id = $this->Book->id - 1;
        $prev_book_id = ($prev_book_id >= 1) ? $prev_book_id : NULL;
        $next_book_id = $this->Book->id + 1;
        $next_book_id = ($next_book_id <= count(config('bss.books_common'))) ? $next_book_id : NULL;
        $PrevBook = $NextBook = NULL;
        $whole_chapter = ($this->chapter_min && strval($this->chapter_min) != $this->chapter_verse);
        $whole_chapter = $this->partial_verses ? true : $whole_chapter;

        if($verbose) {
            $PrevBook = ($prev_book_id) ? $book_class::find($prev_book_id) : NULL;
            $NextBook = ($next_book_id) ? $book_class::find($next_book_id) : NULL;
            $nav['prev_book'] = ($PrevBook) ? $PrevBook->name : NULL;
            $nav['next_book'] = ($NextBook) ? $NextBook->name : NULL;

            if($this->chapter_max == $book_com['chapters']) {
                $nav['next_chapter'] = ($NextBook) ? $NextBook->name . ' 1' : NULL;
                $nav['ncb_name'] = ($NextBook) ? $NextBook->name : NULL;
            }
            else {
                $nav['next_chapter'] = $this->Book->name . ' ' . ($this->chapter_max + 1);
                $nav['ncb_name'] = $this->Book->name;
            }

            if($this->chapter_min == 1) {
                $nav['prev_chapter'] = NULL;

                if($PrevBook) {
                    $prev_com = config('bss.books_common.' . $PrevBook->id);
                    $nav['prev_chapter'] = $PrevBook->name . ' ' . $prev_com['chapters'];
                    $nav['pcb_name'] = $PrevBook->name;
                }
            }
            else {
                $nav['prev_chapter'] = $this->Book->name . ' ' . ($this->chapter_min - 1);
                $nav['pcb_name'] = $this->Book->name;
            }

            $nav['cur_chapter'] = ($whole_chapter) ? $this->Book->name . ' ' . $this->chapter_min : NULL;
            $nav['ccb_name'] = ($whole_chapter) ? $this->Book->name : NULL;
        }

        if($this->chapter_max == $book_com['chapters']) {
            $nav['ncb'] = ($next_book_id) ? $next_book_id : NULL;
            $nav['ncc'] = ($next_book_id) ? 1 : NULL;
        }
        else {
            $nav['ncb'] = $this->Book->id;
            $nav['ncc'] = $this->chapter_max + 1;
        }

        if($this->chapter_min == 1) {
            $nav['pcb'] = $nav['pcc'] = NULL;

            if($prev_book_id) {
                $prev_com = config('bss.books_common.' . $prev_book_id);
                $nav['pcb'] = $prev_book_id;
                $nav['pcc'] = $prev_com['chapters'];
            }
        }
        else {
            $nav['pcb'] = $this->Book->id;
            $nav['pcc'] = $this->chapter_min - 1;
        }

        $nav['ccb'] = ($whole_chapter) ? $this->Book->id : NULL;
        $nav['ccc'] = ($whole_chapter) ? $this->chapter_min : NULL;
        $nav['nb']  = $next_book_id;
        $nav['pb']  = $prev_book_id;

        return $nav;
    }

    public function getPrimaryLanguage() {
        return (is_array($this->languages) && count($this->languages)) ? $this->languages[0] : config('bss.defaults.language_short');
    }

    public function generateVerseIndex() {
        if(empty($this->verses)) {
            return [];
        }

        $index = [];

        foreach($this->verses as $bible => $chapters) {
            foreach($chapters as $chapter => $verses) {
                if(!array_key_exists($chapter, $index)) {
                    $index[$chapter] = [];
                }

                foreach($verses as $verse => $object) {
                    $index[$chapter][] = $verse;
                }
            }
        }

        ksort($index, SORT_NUMERIC);

        foreach($index as &$chapter_verses) {
            $chapter_verses = array_unique($chapter_verses, SORT_NUMERIC);
            sort($chapter_verses, SORT_NUMERIC);
        }
        unset($chapter_verses);

        return $index;
    }

    public function claimVerses(&$results, $retain = FALSE) {
        $this->verses_count = 0;
        $verse_claimed = FALSE;
        $count = [];

        foreach($results as $bible => $verses) {
            $count[$bible] = 0;

            foreach($verses as $key => $verse) {
                if($this->verseInPassage($verse)) {
                    $this->verses[ $bible ][ $verse->chapter ][ $verse->verse ] = $verse;
                    $count[$bible] ++;
                    $verse_claimed = TRUE;
                    $results[$bible][$key]->claimed = TRUE; 

                    if(!$retain) {
                        // unset($results[$bible][$key]);
                    }
                }
            }
        }

        $this->verses_count = ($count) ? max($count) : 0;
        return $verse_claimed;
    }

    public function verseInPassage($verse) 
    {
        $b = $verse->book;

        if(!$this->Book) {
            return false;
        }

        // Check book
        if($this->is_book_range) {
            if($b < $this->Book->id || $b > $this->Book_En->id) {
                return FALSE;
            }
            else {
                // Book Ranges are not set up to handle chapter / verse, so we return TRUE if the passage is in the book range.
                // This is the functionality in V2 and V3.
                return TRUE;
            }
        }
        else if($b != $this->Book->id) {
            return FALSE;
        }

        $parsing = $this->getNormalizedReferences();

        if(empty($parsing)) {
            return TRUE; // Reference is just the book.  If book matches that of verse, then it is in the reference.
        }

        foreach($parsing as $parse) {
            if($parse['type'] == 'single') {
                // Single verses
                if($verse->chapter == $parse['c'] && $verse->verse == $parse['v']) {
                    return TRUE;
                }

                // Chapters
                if($verse->chapter == $parse['c'] && $parse['v'] === NULL) {
                    return TRUE;
                }
            }
            else { // ranges
                $cv    = $verse->chapter * 1000 + $verse->verse;
                $cv_st = $parse['cst'] * 1000 + $parse['vst'];
                $cv_en = $parse['cen'] * 1000 + $parse['ven'];

                if($cv >= $cv_st && $cv <= $cv_en) {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    /**
     * Indicates if this is a reference to exactly ONE verse.  (IE: 1 John 1:1)
     */

    public function isSingleVerse() 
    {
        $parsing = $this->chapter_verse_parsed;

        if(count($parsing) == 1) {
            return ($parsing[0]['type'] == 'single' && $parsing[0]['v'] !== NULL);
        }

        return FALSE;
    }

    /**
     * Indicates if, after claiming verses, passage only has ONE verse
     *
     * @return bool
     */
    public function containsSingleVerse() 
    {
        return ($this->verses_count == 1);
    }

    public function isSingleBook() 
    {
        if($this->is_book_range) {
            return FALSE;
        }

        return (empty($this->getNormalizedReferences()));
    }

    /**
     * Indicates that the passage reference contains reference to chapter(s) only
     * and not to individual verses
     *
     * @return bool
     */
    public function isChapterOnly() {
        return (strpos($this->chapter_verse, ':') === FALSE);
    }

    public function explodePassage($separate_book_ranges, $separate_chapters) {
        if($separate_book_ranges && $this->is_book_range) {
            $Passages = [];

            for($book = $this->Book->id; $book <= $this->Book_En->id; $book ++) {
                $Passage = clone $this;
                $Passage->setBookById($book);
                $Passages[] = $Passage;
            }

            return $Passages;
        }

        if($separate_chapters && count($this->chapter_verse_parsed)) {
            $Passages = [];

            foreach($this->chapter_verse_parsed as $parsed) {
                if($parsed['type'] == 'single' || $parsed['type'] == 'range' && $parsed['cst'] == $parsed['cen']) {
                    $Passage = clone $this;
                    $Passage->setChapterVerseFromParsed($parsed);
                    $Passages[] = $Passage;
                }
                else {
                    $cst = ($parsed['cst']) ? $parsed['cst'] : 1;
                    $cen = ($parsed['cen']) ? $parsed['cen'] : 999;
                    $parsed_st = $parsed_en = $parsed;

                    $parsed_st['cst'] = $cst;
                    $parsed_st['cen'] = $cst;
                    $parsed_st['ven'] = NULL;
                    $parsed_en['cst'] = $cen;
                    $parsed_en['vst'] = NULL;
                    $cvst = $cven = '';

                    if($parsed['cst']) {
                        $cvst  = $parsed['cst'];
                        $cvst .= ($parsed['vst']) ? ':' . $parsed['vst'] : '';
                        $cvst .= '-';
                    }

                    if($parsed['cen']) {
                        $cven  = '-' . $parsed['cen'];
                        $cven .= ($parsed['ven']) ? ':' . $parsed['ven'] : '';
                    }

                    $Passage = clone $this;
                    $Passage->setChapterVerseFromParsed($parsed_st);
                    $Passages[] = $Passage;

                    for($chapter = $cst + 1; $chapter < $cen; $chapter ++) {
                        $Passage = clone $this;
                        $Passage->setChapterVerse($chapter);
                        $Passages[] = $Passage;
                    }

                    $Passage = clone $this;
                    $Passage->setChapterVerseFromParsed($parsed_en);
                    $Passages[] = $Passage;
                }
            }

            return $Passages;
        }

        return [$this];
    }

    public function explodePassageByChapters() {
        $Exploded = [];

        if(empty($this->verses) || !$this->isSingleBook()) {
            return [$this];
        }

        $verses_by_chapter = [];

        foreach($this->verses as $bible => $vbb) {
            foreach($vbb as $ch => $verses) {
                if(!isset($verses_by_chapter[$ch])) {
                    $verses_by_chapter[$ch] = [];
                }
                
                $verses_by_chapter[$ch][$bible] = $verses;
            }
        }

        foreach($verses_by_chapter as $ch => $vbb) {
            $Passage = static::createFromChapter($this->Book->id, $ch, $this->languages, $this->is_search);
            $Passage->claimVerses($vbb);
            $Passage->partial_verses = true;
            $Exploded[] = $Passage;
        }

        return $Exploded;
    }

    public function getStatistics($input = [])
    {
        $by_bible = [];

        foreach($this->Bibles as $Bible) {
            $by_bible[$Bible->module] = $Bible->getStatistics($this, $input);
        }

        return $by_bible;
    }

    /**
     * Parses the reference string from the user into references that can be used by the query
     * @param string $reference
     * @param array $languages - languages to check (short names)
     * @param bool $is_search - whether the parser should interpret this as a search
     * @return array|bool $Passages - array of passage instances, or FALSE if nothing parsed
     */
    public static function parseReferences($reference, $languages = [], $is_search = FALSE, $Bibles = [], $parameters = []) 
    {
        if(!is_string($reference) || empty($reference)) {
            return FALSE;
        }

        $Passages   = [];
        $pre_parsed = static::explodeReferences($reference);

        $def_language = config('bss.defaults.language_short');

        if(!in_array($def_language, $languages)) {
            $languages[] = $def_language;
        }

        if($is_search !== 2) {
            foreach($pre_parsed as $key => &$ref) {
                $ref = static::findShortcut($ref, $languages, TRUE);
                $ref = static::findVerseIds($ref, $Bibles);
            }
            unset($ref);
        }

        $mid_parsed = implode(';', $pre_parsed);
        $parsed = static::explodeReferences($mid_parsed, TRUE);

        foreach($parsed as $ref) {
            $Passages[] = self::parseSingleReference($ref['book'], $ref['chapter_verse'], $languages, $is_search, $Bibles, $parameters);
        }

        return (empty($Passages)) ? FALSE : $Passages;
    }

    public static function explodeReferences($reference, $separate_book = FALSE) {
        $exploded = [];
        $ref_end  = strlen($reference) - 1;
        $book_end = FALSE;

        for($pos = $ref_end; $pos >= 0; $pos --) {
            $char = $reference[$pos];

            if(!$book_end && !static::isChapterVerse($char) && !static::isWhitespace($char)) {
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

                $ref_end  = $pos;
                $book_end = FALSE;
            }
        }

        if($ref_end !== 0) {
            //$exploded[] = substr($reference, 0, $ref_end - 1);
        }

        // To keep the references in the same order that they were submitted
        $exploded = array_reverse($exploded);
        return $exploded;
    }

    protected static function findVerseIds($ref, $Bibles)
    {
        $Bible = $Bibles ? array_values($Bibles)[0] : Bible::findByModule(config('bss.defaults.bible'));
        $last_book = null;

        $ref = preg_replace_callback('/(-\s*)?([0-9]{1,5})V/', function($matches) use ($Bible, &$last_book) {
            $vid = (int) $matches[2];
            $Verse = $Bible->verses()->find($vid);
            $dash = $matches[1] ? '-' : '';

            if($Verse) {
                $str = $Verse->chapter . ':' . $Verse->verse;

                if($last_book != $Verse->book) {
                    // if($dash && $Verse->book - $) {
                    //     $lb1 = $last_book ++;
                    //     $vb1 = $Verse->book
                    // }
                    // else {
                        $str = $Verse->book . 'B ' . $str;
                    // }

                    $last_book = $Verse->book;
                }

                return $dash . $str;
            }
            else {
                $last_book = null;
            }

            return $matches[0];
        }, $ref);

        return $ref;
    }

    /**
     * Locale agnostic replacement for ctype_alpha
     */ 
    public static function isAlpha($char) 
    {
        $found = preg_match('/[\p{L}\p{M}\p{Pd}]+/', $char, $matches);

        if(!$found) {
            return false;
        }

        return true;

        // $extra = preg_match('/[^\p{L}]+/', $char, $matches);
        // return !$extra; 
    }

    public static function isChapterVerse($char)
    {
        return is_numeric($char) || in_array($char, [':',',',';','-']);
    }

    public static function isWhitespace($char) {
        return $char == ' ';
    }

    public static function isRandom($reference) {
        $reference = strtolower($reference);
        $reference = str_replace(' ', '_', $reference);

        $randoms = ['random_book', 'random_chapter', 'random_verse'];

        foreach($randoms as $rand) {
            if(strpos($reference, $rand) === 0) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public static function normalizeRandom($reference) {
        $ref = strtolower($reference);
        $ref = str_replace(' ', '_', $ref);
        $randoms = ['random_book', 'random_chapter', 'random_verse'];

        foreach($randoms as $rand) {
            if(strpos($ref, $rand) === 0) {
                return $rand;
            }
        }

        return $ref;
    }

    /**
     * Parses out the string for a single passage reference
     * @param string $book - string reppresenting the book of the passage reference
     * @param string $chapter_verse - string representing the chapter and verse references
     * @return \App\Passage
     */
    public static function parseSingleReference($book, $chapter_verse, $languages = [], $is_search = FALSE, $Bibles = [], $parameters = []) {
        $Passage = new static;
        $Passage->languages = $languages;
        $Passage->is_search = $is_search;
        $Passage->is_contextual = (array_key_exists('context', $parameters) && $parameters['context'] == TRUE);
        $Passage->contextual_range = (array_key_exists('context_range', $parameters)) ? (int) $parameters['context_range'] : 5;
        $Passage->Bibles = $Bibles;
        $Passage->setBook($book);
        $Passage->setChapterVerse($chapter_verse);
        return $Passage;
    }

    /**
     * Creates a single-verse passage from a verse
     *
     * @param stdClass $verse
     * @return \App\Passage
     */
    public static function createFromVerse($verse, $languages = [], $is_search = FALSE) {
        $Passage = new static;
        $Passage->languages = $languages;
        $Passage->is_search = $is_search;
        $Passage->setBookById($verse->book);
        $Passage->setChapterVerse($verse->chapter . ':' . $verse->verse);
        return $Passage;
    }    

    /**
     * Creates a passage for the given book_id and chapter
     *
     * @param int $book_id
     * @param int $chapter
     * @return \App\Passage
     */
    public static function createFromChapter($book_id, $chapter, $languages = [], $is_search = FALSE) {
        $Passage = new static;
        $Passage->languages = $languages;
        $Passage->is_search = $is_search;
        $Passage->setBookById($book_id);
        $Passage->setChapterVerse($chapter);
        return $Passage;
    }

    public static function explodePassages($Passages = [], $separate_book_ranges = TRUE, $separate_chapters = FALSE) {
        $Exploded = [];

        foreach($Passages as $Passage) {
            $Exploded = array_merge($Exploded, $Passage->explodePassage($separate_book_ranges, $separate_chapters));
        }

        return $Exploded;
    }    

    public static function explodePassagesByChapters($Passages = []) {
        $Exploded = [];

        foreach($Passages as $Passage) {
            $Exploded = array_merge($Exploded, $Passage->explodePassageByChapters());
        }

        return $Exploded;
    }

    public static function implodePassages($VersePassages, $RequestedPassages) {
        $Imploded = [];
    }

    /**
     * Maps the generic 'request' input to either the search keywords or the reference input
     * But not both
     *
     * @param string $input
     * @param array $languages
     * @param array $Bibles
     * @return array containing $keywords and $reference
     */
    public static function mapRequest($input, $languages, $Bibles) 
    {
        $reference  = empty($input['reference']) ? NULL : Helpers::trimRequest($input['reference']);
        $keywords   = empty($input['search'])    ? NULL : Helpers::trimRequest($input['search']);
        $request    = empty($input['request'])   ? NULL : Helpers::trimRequest($input['request']);

        $disambiguation = [];
        $has_disambiguation_book = FALSE;

        if(!empty($request)) {
            if(!$keywords && !$reference) {
                $request_org = $request;
                $non_passage_chars = static::_containsNonPassageCharacters($request);

                $passages = static::explodeReferences($request, TRUE);

                // $Passages = static::parseReferences($request, $languages, FALSE, $Bibles); // Worst case senariao - lots of overhead

                // Treats as passage if 
                // * It's not empty AND 
                // * doesn't contain Strong's Numbers AND
                // * doesn't contain invalid characters for a reference (such as those used for boolean or REGEXP queries) AND 
                // * either
                //      1) It contains numbers but no (parentheses) or
                //      2) It resolves to multiple (possible) passages
                // Note: This passage-checking logic is specific to this method
                if(
                    // static::isPossiblePassage($request)
                    !empty($passages) && 
                    !(preg_match('/[GHgh][0-9]+/', $request)) && 
                    !$non_passage_chars && 
                    ( (preg_match('/[0-9]/', $request) && strpos($request, '(') === FALSE) || count($passages) >= 2)
                ) {
                    $reference = $request_org;
                }
                // Otherwise, treats it as search keywords
                else {
                    $keywords = $request_org;

                    // Check for a single book
                    $Books = static::findBookByNameAndLanguage($request, $languages, TRUE);

                    if($Books && is_array($Books)) {
                        $has_disambiguation_book = TRUE;

                        foreach($Books as $Book) {
                            $disambiguation[] = [
                                'description' => 'Were you looking for the Book of ' . $Book->name . '?',
                                'simple' => $Book->name,
                                'type' => 'book',
                                'data' => [
                                    'reference' => $Book->name,
                                    'bible'     => $input['bible'],
                                ],
                            ];
                        }
                    }
                }
            }
            elseif($keywords && !$reference) {
                $reference = $request;
            }
            elseif($reference && !$keywords) {
                if(static::isPassageStrict($request, $languages)) {
                    $reference = $request;
                }
                else {
                    $keywords = $request;
                }
            }
        }

        return array($keywords, $reference, $disambiguation, $has_disambiguation_book);
    }

    public static function isPassage($string, $languages, $strict = FALSE) {
        $passages = static::explodeReferences($string, TRUE);

        if(!static::_isPossiblePassageHelper($string, $passages)) {
            return FALSE;
        }

        $has_valid_passage = FALSE;
        $all_valid_passage = TRUE;

        foreach($passages as $passage) {
            $Book = static::findBookByNameAndLanguage($passage['book'], $languages);

            if($strict) {
                $has_valid_passage = ( $Book &&  $passage['chapter_verse']) ? TRUE  : $has_valid_passage;
                $all_valid_passage = (!$Book || !$passage['chapter_verse']) ? FALSE : $all_valid_passage;
            }
            else {            
                $has_valid_passage = ($Book)  ? TRUE  : $has_valid_passage;
                $all_valid_passage = (!$Book) ? FALSE : $all_valid_passage;
            }

        }

        return $has_valid_passage;
    }

    public static function isPossiblePassage($string) {
        $passages = static::explodeReferences($string, TRUE);
        return static::_isPossiblePassageHelper($string, $passages);
    }

    /*
     * Unlike static::isPassage() this requires the passage to have at least a chapter
     */
    public static function isPassageStrict($string, $languages) {
        return static::isPassage($string, $languages, TRUE);
    }

    protected static function _isPossiblePassageHelper($string, $passages) {
        $non_passage_chars = static::_containsNonPassageCharacters($string);

        // $Passages = static::parseReferences($request, $languages, FALSE, $Bibles); // Worst case senariao - lots of overhead

        // Treats as passage if 
        // * It's not empty AND 
        // * doesn't contain Strong's Numbers AND
        // * doesn't contain invalid characters for a reference (such as those used for boolean or REGEXP queries) 
        if(empty($passages)) {
            return FALSE;
        }

        if(preg_match('/[GHgh][0-9]+/', $string)) {
            return FALSE; // Contains Strong's #
        }

        if($non_passage_chars) {
            return FALSE;
        }

        return TRUE;
    }

    public static function _containsNonPassageCharacters($string) {
        $non_passage_chars = preg_match('/[`\'"\\~!@#$%\^&*{}_[\]()=]/', $string, $matches);
        // $non_passage_chars = preg_match_all('/[\p{Ps}\p{Pe}\(\)\\\|\+&]/', $string, $matches); // BookAbstract::findByEnteredName
        // $non_passage_chars = preg_match_all('/[^0-9\p{L}\p{M} :,.-]/', $string, $matches);
        // $non_passage_chars = preg_match_all('/[`\\~!@#$%\^&*{}_[\]]/', $string, $matches);

        return (bool) $non_passage_chars;
    }
}
