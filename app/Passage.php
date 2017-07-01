<?php

namespace App;

use App\Models\Books\BookAbstract as Book;
use App\Models\Shortcuts\ShortcutAbstract as Shortcut;

/**
 * Class for parsing passage references
 */

class Passage {

    use Traits\Error;

    public $is_search = FALSE;
    protected $Book;    // Book instance - Single or Start of range
    protected $Book_En; // Book instance - Range end
    protected $is_book_range = FALSE;
    protected $raw_reference; // Reference as entered by user
    protected $raw_book;          // Book as entered by user
    protected $raw_chapter_verse; // Chapter and verse as entered by user
    protected $chapter_verse; // Chapter and verse part of reference
    protected $chapter_verse_parsed; // Chapter and verse, parsed into an array of arrays
    protected $chapter_max; // Maximum chapter number requested
    protected $chapter_min; // Minimum chapter number requested
    protected $verses; // Array of verses, grouped by bible, book, chapter, verse as found by the query
    protected $verses_index; // Array of book / chapter / verse
    protected $verses_count = 0; // Count of the verses matched to this passage (as found by the query).
    protected $languages; // Array of language short names
    protected $Bibles = array(); //Array of Bibles
    protected $is_valid = FALSE; // Is the provided reference valid?
    protected $is_random = FALSE; // Is the user requesting a random chapter or verse?

    public function __construct() {
        // Do something?
    }

    public function setBookById($book_id) {
        $language = (is_array($this->languages) && count($this->languages)) ? $this->languages[0] : env('DEFAULT_LANGUAGE_SHORT', 'en');
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
            if(!$this->is_search) {
                return $this->_addBookError(trans('errors.book.multiple_without_search'));
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
                $this->clearChapterVerse();
            }
            else {
                return $this->_addBookError(trans('errors.book.invalid_in_range', ['range' => $book]));
            }
        }
        else {
            $this->is_book_range = FALSE;
            $Book = $this->findBook($book);

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

        $cb_raw = $this->raw_chapter_verse;
        $this->setBookById($rand['book_id']);
        $this->setChapterVerse($rand['chapter_verse']);
        $this->raw_chapter_verse = $cb_raw;
        $this->is_random = TRUE;
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

    public function setChapterVerse($chapter_verse) {
        if($this->is_random) {
            return;
        }

        $this->clearChapterVerse();
        $this->raw_chapter_verse = preg_replace('/\s+/', ' ', $chapter_verse);
        $chapter_verse = str_replace([';',' '], [',',''], $chapter_verse);
        $chapter_verse = preg_replace('/,+/', ',', $chapter_verse); // Replace repeated , with one ,
        $chapter_verse = preg_replace('/-+/', '-', $chapter_verse); // Replace repeated - with one -
        $chapter_verse = preg_replace('/:+/', ':', $chapter_verse); // Replace repeated : with one :
        $chapter_verse = (!$this->is_search && empty($chapter_verse)) ? '1' : $chapter_verse;
        $this->chapter_verse = $chapter_verse;

        $chapters = array();

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
                            $parsed[] = array('c' => $current_chapter, 'v' => $current_verse, 'type' => 'single');
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

    public function setChapterVerseFromParsed($parsed_item) {
        $this->clearChapterVerse();
        $chapter_verse = '';

        if($parsed_item['type'] == 'range') {
            if($parsed_item['cst']) {
                $chapter_verse .= ($parsed_item['cst']);
                $chapter_verse .= ($parsed_item['vst']) ? ':' . $parsed_item['vst'] : '';
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
            $chapter_verse .= ($parsed_item['c']) ? $parsed_item['c'] : '';
            $chapter_verse .= ($parsed_item['v']) ? ':' . $parsed_item['v'] : '';
        }

        $this->chapter_verse = $chapter_verse;
        $this->chapter_verse_parsed = array($parsed_item);
    }

    public function clearChapterVerse() {
        $this->chapter_verse_parsed = array();
        $this->raw_chapter_verse = NULL;
        $this->chapter_verse = NULL;
        $this->chapter_max = NULL;
        $this->chapter_min = NULL;
        $this->verses = array();
        $this->verses_count = 0;
    }

    public function getNormalizedReferences() {
        $parsed = $this->chapter_verse_parsed;

        if(!is_array($parsed)) {
            return array();
        }

        foreach($parsed as &$part) {
            if(isset($part['type']) && $part['type'] == 'range') {
                $part['vst'] = ($part['vst']) ? $part['vst'] : 0;
                $part['ven'] = ($part['ven']) ? $part['ven'] : 999;
                $part['cst'] = ($part['cst']) ? $part['cst'] : 1;
                $part['cen'] = ($part['cen']) ? $part['cen'] : 999;
            }
        }

        return $parsed;
    }

    public function getAdjustedReferences() {
        $adjusted = $pre = array();

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
            $v = array();
            $last_verse = $first_verse = NULL;

            foreach($verses as $verse => $s) {
                $first_verse = ($first_verse) ? $first_verse : $verse;

                if($last_verse && $verse != $last_verse + 1) {
                    $v[] = ($first_verse == $last_verse) ? $first_verse : $first_verse . ' - ' . $verse;
                    $first_verse = $verse;
                }

                $last_verse = $verse;
            }

            $v[] = ($first_verse == $last_verse) ? $first_verse : $first_verse . ' - ' . $verse;
            $adjusted[] = $chapter . ':' . implode(',', $v);
        }

        return implode('; ', $adjusted);
    }

    public function getAdjustedChapterVerse() {

    }

    public function __set($name, $value) {
        $settable = ['languages', 'is_search', 'Bibles'];

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

    public function toArray() {
        $passage = array(
            'book_id'           => $this->Book->id,
            'book_name'         => $this->Book->name,
            'book_short'        => $this->Book->shortname,
            'book_raw'          => $this->raw_book,
            'chapter_verse'     => $this->getAdjustedReferences(),
            //'chapter_verse'     => $this->chapter_verse,
            'chapter_verse_raw' => $this->raw_chapter_verse,
            'verse_index'       => $this->generateVerseIndex(),
            'verses'            => $this->verses,
            'verses_count'      => $this->verses_count,
            //'single_verse'      => $this->isSingleVerse(),
            'single_verse'      => $this->containsSingleVerse(),
            //'chapter_verse_parsed' => $this->chapter_verse_parsed, // Debugging only
        );

        if($this->is_book_range) {
            $passage['book_name']  .= ' - ' . $this->Book_En->name;
            $passage['book_short'] .= ' - ' . $this->Book_En->shortname;
        }

        return $passage;
    }

    public function generateVerseIndex() {
        if(empty($this->verses)) {
            return array();
        }

        $index = array();

        foreach($this->verses as $bible => $chapters) {
            foreach($chapters as $chapter => $verses) {
                if(!array_key_exists($chapter, $index)) {
                    $index[$chapter] = array();
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
        $count = array();

        foreach($results as $bible => $verses) {
            $count[$bible] = 0;

            foreach($verses as $key => $verse) {
                if($this->verseInPassage($verse)) {
                    $this->verses[ $bible ][ $verse->chapter ][ $verse->verse ] = $verse;
                    //$this->verses_count ++;
                    $count[$bible] ++;
                    $verse_claimed = TRUE;

                    if(!$retain) {
                        unset($results[$bible][$key]);
                    }
                }
            }
        }

        $this->verses_count = ($count) ? max($count) : 0;
        return $verse_claimed;
    }

    public function verseInPassage($verse) {
        $b = $verse->book;

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

    public function isSingleVerse() {
        $parsing = $this->getNormalizedReferences();

        if(count($parsing) == 1) {
            //return ($parsing[0]['type'] == 'single') ? TRUE : FALSE;
            return ($parsing[0]['type'] == 'single' && $parsing[0]['v'] !== NULL) ? TRUE : FALSE;
        }

        return FALSE;
    }

    /**
     * Indicates if, after claiming verses, passage only has ONE verse
     *
     * @return bool
     */
    public function containsSingleVerse() {
        return ($this->verses_count == 1) ? TRUE : FALSE;
    }

    public function isSingleBook() {
        if($this->is_book_range) {
            return FALSE;
        }

        return (empty($this->getNormalizedReferences())) ? TRUE : FALSE;
    }

    public function explodePassage($separate_book_ranges, $separate_chapters) {
        if($separate_book_ranges && $this->is_book_range) {
            $Passages = array();

            for($book = $this->Book->id; $book <= $this->Book_En->id; $book ++) {
                $Passage = clone $this;
                $Passage->setBookById($book);
                $Passages[] = $Passage;
            }

            return $Passages;
        }

        if($separate_chapters && count($this->chapter_verse_parsed)) {
            $Passages = array();

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
                    //$Passage->setChapterVerse($cvst);
                    $Passage->setChapterVerseFromParsed($parsed_st);
                    $Passages[] = $Passage;

                    for($chapter = $cst + 1; $chapter < $cen; $chapter ++) {
                        //var_dump($chapter);
                        $Passage = clone $this;
                        $Passage->setChapterVerse($chapter);
                        $Passages[] = $Passage;
                    }

                    $Passage = clone $this;
                    //$Passage->setChapterVerse($cven);
                    $Passage->setChapterVerseFromParsed($parsed_en);
                    $Passages[] = $Passage;

                    //var_dump('passage', count($Passages));
                }
            }

            return $Passages;
        }

        return array($this);
    }

    /**
     * Parses the reference string from the user into references that can be used by the query
     * @param string $reference
     * @param array $languages - languages to check (short names)
     * @param bool $is_search - whether the parser should interpret this as a search
     * @return array|bool $Passages - array of passage instances, or FALSE if nothing parsed
     */
    public static function parseReferences($reference, $languages = array(), $is_search = FALSE, $Bibles = array()) {
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
            $Passages[] = self::parseSingleReference($ref['book'], $ref['chapter_verse'], $languages, $is_search, $Bibles);
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

    public static function isRandom($reference) {
        $reference = strtolower($reference);
        $reference = str_replace(' ', '_', $reference);

        $randoms = ['random_chapter', 'random_verse'];

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

        $randoms = ['random_chapter', 'random_verse'];

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
    public static function parseSingleReference($book, $chapter_verse, $languages = array(), $is_search = FALSE, $Bibles = array()) {
        $Passage = new static;
        $Passage->languages = $languages;
        $Passage->is_search = $is_search;
        $Passage->Bibles = $Bibles;
        $Passage->setBook($book);
        $Passage->setChapterVerse($chapter_verse);
        return $Passage;
    }

    /**
     * Creates a single-verse passage from a verse
     *
     * @param type $verse
     * @return \App\Passage
     */
    public static function createFromVerse($verse, $languages = array(), $is_search = FALSE) {
        $Passage = new static;
        $Passage->languages = $languages;
        $Passage->is_search = $is_search;
        $Passage->setBookById($verse->book);
        $Passage->setChapterVerse($verse->chapter . ':' . $verse->verse);
        return $Passage;
    }

    public static function explodePassages($Passages = array(), $separate_book_ranges = TRUE, $separate_chapters = FALSE) {
        $Exploded = array();

        foreach($Passages as $Passage) {
            $Exploded = array_merge($Exploded, $Passage->explodePassage($separate_book_ranges, $separate_chapters));
        }

        return $Exploded;
    }
}
