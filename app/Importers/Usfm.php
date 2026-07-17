<?php

namespace App\Importers;
use App\Models\Bible;
use \DB; 
use ZipArchive;
use Illuminate\Http\UploadedFile;

/*
 * 'USFM' importer
 *
 *
 */

//[brackets] are for Italicized words
//
//<brackets> are for the Words of Christ in Red
//
//«brackets»  are for the Titles in the Book  of Psalms.

class Usfm extends ImporterAbstract 
{
    protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '\add ';
    protected $italics_en   = '\add*';
    protected $redletter_st = '\wj ';
    protected $redletter_en = '\wj*';
    protected $strongs_st   = NULL; // Needs special parsing
    protected $strongs_en   = NULL; // Needs special parsing
    protected $paragraph    = NULL;
    protected $path_short   = 'usfm';

    protected $book_map = [
        'GEN' => 1,
        'EXO' => 2,
        'LEV' => 3,
        'NUM' => 4,
        'DEU' => 5,
        'JOS' => 6,
        'JDG' => 7,
        'RUT' => 8,
        '1SA' => 9,
        '2SA' => 10,
        '1KI' => 11,
        '2KI' => 12,
        '1CH' => 13,
        '2CH' => 14,
        'EZR' => 15,
        'NEH' => 16,
        'EST' => 17,
        'JOB' => 18,
        'PSA' => 19,
        'PRO' => 20,
        'ECC' => 21,
        'SNG' => 22,
        'ISA' => 23,
        'JER' => 24,
        'LAM' => 25,
        'EZK' => 26,
        'DAN' => 27,
        'HOS' => 28,
        'JOL' => 29,
        'AMO' => 30,
        'OBA' => 31,
        'JON' => 32,
        'MIC' => 33,
        'NAM' => 34,
        'HAB' => 35,
        'ZEP' => 36,
        'HAG' => 37,
        'ZEC' => 38,
        'MAL' => 39,
        'MAT' => 40,
        'MRK' => 41,
        'LUK' => 42,
        'JHN' => 43,
        'ACT' => 44,
        'ROM' => 45,
        '1CO' => 46,
        '2CO' => 47,
        'GAL' => 48,
        'EPH' => 49,
        'PHP' => 50,
        'COL' => 51,
        '1TH' => 52,
        '2TH' => 53,
        '1TI' => 54,
        '2TI' => 55,
        'TIT' => 56,
        'PHM' => 57,
        'HEB' => 58,
        'JAS' => 59,
        '1PE' => 60,
        '2PE' => 61,
        '1JN' => 62,
        '2JN' => 63,
        '3JN' => 64,
        'JUD' => 65,
        'REV' => 66,
    ];

    protected function _importHelper(Bible &$Bible): bool
    {
        ini_set("memory_limit", "500M");

        $dir    = $this->getImportDir();
        $file   = $this->file;   // File name, minus extension
        $module = $this->module; // Module and db name

        if($this->debug) {
            $file = 'ne_ulb_npiulb_usfm.zip';
            $this->bible_attributes['name'] = $this->module;
            $this->bible_attributes['lang_short'] = 'ne';
            $this->bible_attributes['lang'] = 'gu';
            $Bible = $this->_getBible($this->module);
        }

        $attr   = $this->bible_attributes;

        $zipfile = $dir . $file;

        if(!$this->overwrite && $this->_existing && $this->insert_into_bible_table) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        $Zip = new ZipArchive;

        if(\App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if($Zip->open($zipfile) === TRUE) {
            // Not importing any metadata at this time!
            if($this->insert_into_bible_table) {
                $desc  = $Zip->getFromName('copr.htm') ?: null;

                $attr['description'] = $desc ?: null;
                $Bible->fill($attr);
                $Bible->save();
            }

            $Bible->install(TRUE);

            for($i = 0; $i < $Zip->numFiles; $i++) {
                $filename = $Zip->getNameIndex($i);
                $this->_zipImportHelper($Zip, $filename);
            }

            $Zip->close();
        }
        else {
            return $this->addError('Unable to open ' . $zipfile, 4);
        }

        $this->_insertVerses();

        return true;
    }

    protected function _zipImportHelper(&$Zip, $filename)
    {
        $spl = explode('.', $filename);
        $ext = strtolower(array_pop($spl));

        if($ext != 'usfm' && $ext != 'sfm') {
            return false;
        }

        $chapter = $verse = NULL;

        $next_line_para = FALSE;
        $bib = $Zip->getFromName($filename);

        if($bib === false) {
            return false;
        }

        if(substr($bib, 0, 3) === "\xEF\xBB\xBF") {
            $bib = substr($bib, 3); // strip UTF-8 BOM (common in Paratext exports)
        }

        $bib = preg_split("/\\r\\n|\\r|\\n/", $bib);

        // Books are delimited by \id markers; content before the first valid one is ignored.
        $book = null;
        $id_found = $book_found = false;

        $text = null;
        $end_of_verse = false;

        $book_meta = [
            'name_long' => null,
            'name'      => null,
            'shortname' => null,
        ];

        foreach($bib as $key => $line) {
            $line = trim($line);
            $line_lookahead = isset($bib[$key + 1]) ? trim($bib[$key + 1]) : null;

            // New book ("\id " with trailing space, so \ide et al don't match)
            if(preg_match('/^\\\\id\s/', $line)) {
                $id_found = true;

                // Flush the previous book's final verse before resetting state
                if($verse !== null) {
                    $this->_addVerse($book, $chapter, $verse, $text, true);
                }

                if($book) {
                    $this->book_metas[$book] = $book_meta; // Save the previous book's metadata
                }

                $book_meta = [
                    'name_long' => null,
                    'name'      => null,
                    'shortname' => null,
                ];

                // null = non-canonical book (apocrypha / front matter / glossary); its content is skipped
                $book = $this->getBookFromBookLine($line);

                if($book) {
                    $book_found = true;
                }

                $chapter = $verse = null;
                $text = null;
                $next_line_para = false;

                continue;
            }

            // Chapter number: \c only. \ca, \cl, \cp, \cd are chapter *metadata*
            // whose digits must never be mistaken for the chapter number.
            if(preg_match('/^\\\\c(\s|$)/', $line)) {
                if(preg_match('/^\\\\c\s+([0-9]+)/', $line, $matches)) {
                    $chapter = (int) $matches[1];
                }

                continue;
            }

            if(preg_match('/^\\\\c[alpd](\s|$)/', $line)) {
                continue; // \ca, \cl, \cp, \cd - chapter metadata we do not use
            }


            if(strpos($line, '\toc1') === 0) {
                $book_meta['name_long'] = trim(substr($line, 5));
                continue;
            }

            if(strpos($line, '\toc2') === 0) {
                $book_meta['name'] = trim(substr($line, 5));
                continue;
            }

            if(strpos($line, '\toc3') === 0) {
                $book_meta['shortname'] = trim(substr($line, 5));
                continue;
            }

            if(preg_match('/^\\\\rem(\s|$)/', $line)) {
                continue; // editorial remark - never published, must not bleed into verse text
            }

            // continue; // debugging - bypass actual Bible import
            
            // Paragraph marker family (\p, \pi#, \pc, \pm(c|o|r), \po, \pr, \pb, \ph#)
            // - but not unrelated \p-prefixed markers like \periph or \pn
            if(preg_match('/^\\\\p(i[0-9]?|c|m[cor]?|o|r|b|h[0-9]?)?(\s|$)/', $line)) {
                $next_line_para = TRUE;
            }

            if(preg_match('/^\\\\v\s/', $line)) {
                // "\v 1 text" => verse 1 / "text"; text is empty when the verse starts on the next line
                if(preg_match('/^\\\\v\s+(\S+)\s*(.*)/', $line, $vm)) {
                    $verse = (int) $vm[1];
                    $text  = $vm[2];

                    if($next_line_para) {
                        $text = '¶ ' . $text;
                        $next_line_para = FALSE;
                    }
                }
            } else if($verse !== null) {
                // Continuation line of the current verse (verse text spanning multiple lines).
                $text = ($text === null || $text === '') ? $line : $text . ' ' . $line;
            }

            // End of verse when the next line starts a chapter, verse, or heading-type
            // marker. Family prefixes are intentional (\s* headings, \mt*/\ms* titles),
            // but \rem is excluded: a remark does not end the verse (its line is skipped
            // above, and the verse text may continue after it).
            if(!$line_lookahead || preg_match('/^\\\\(c|v|s|mt|ms|r(?!em)|d|qa)/', $line_lookahead)) {
                $end_of_verse = true;
            }

            if($end_of_verse) {
                $this->_addVerse($book, $chapter, $verse, $text, true);
                $end_of_verse = false;
                $text = null;
                $verse = null;
            }
        }

        if($book) {
            $this->book_metas[$book] = $book_meta;
        }

        if(!$id_found) {
            $this->_echoIfConsole('Skipping ' . $filename . ': no valid \\id line found.');

            return false; // No valid \id line - not an importable book file
        }

        if(!$book_found) {
            $this->_echoIfConsole('Skipping ' . $filename . ': no valid book line found.');

            return false; // No canonical books - not an importable Bible book file
        }

        return true;
    }

    protected function getBookFromBookLine(string $line) : ?int
    {
        if(preg_match('/^\\\\id\s+(\w{3})/', $line, $m)) {
            $book_str = strtoupper($m[1]);

            if(isset($this->book_map[$book_str])) {
                return $this->book_map[$book_str];
            }
        }

        return null;
    }

    public function checkUploadedFile(UploadedFile $File): bool 
    {
        $zipfile    = $File->getPathname();
        $file       = static::sanitizeFileName( $File->getClientOriginalName() );
        $Zip        = new ZipArchive();

        if(stripos($file, 'sfm') === false) {
            return $this->addError('Does not appear to be a USFM file; filename does not end with "usf" or "usfm".');
        }

        $allowed = [
            'copr.htm',
            'keys.asc',
            'signature.txt.asc',
        ];

        $book_count = 0;

        // ZipArchive::open() returns TRUE on success or an int error code on failure -
        // a loose == true comparison would treat error codes as success.
        if($Zip->open($zipfile) !== true) {
            return $this->addError('Does not appear to be a valid USFM file; unable to open ZIP file.');
        }

        for ($i = 0; $i < $Zip->numFiles; $i++) {
            $filename = $Zip->getNameIndex($i);
            $spl = explode('.', $filename);
            $ext = strtolower(array_pop($spl));

            if($ext == 'usfm' || $ext == 'sfm') {
                $book_count++;
            }
        }

        if($book_count == 0) {
            return $this->addError('Does not appear to be a valid USFM file; ZIP file does not contain any .usfm or .sfm files.');
        }

        // locateName() returns the entry index, which can be 0 (falsy) for the first entry
        if($Zip->locateName('copr.htm') !== false) {
            $desc  = $Zip->getFromName('copr.htm');
        } else {
            $desc = null;
        }

        $this->bible_attributes = [
            'description' => $desc,
        ];

        return true;
    }

    protected function _formatStrongs($text)
    {
        // Currently included:
        // \p{L}: any kind of letter from any language.
        // \p{M}: a character intended to be combined with another character (e.g. accents, umlauts, enclosing boxes, etc.).
        // \p{N}: any kind of numeric character in any script.

        // clean up to handle strongs within red-letter words
        $text = str_replace('\+w', '\w', $text); 
        $text = str_replace('\+w*', '\w*', $text);

        // custom strongs handling here
        $pattern = "/\\\w (.+?)\\\w\*/"; // works for non-red words

        $text = preg_replace_callback($pattern, function($matches) {
            // Note: strong is the only word attribute we use, we discard all others!
            $m = $matches[1] ?? '';
            $parts = explode('|', $m);

            $word = $parts[0] ?? '';
            $attr = $parts[1] ?? '';

            $strong_pos = strpos($attr, 'strong');

            if($strong_pos === false) {
                return $word;
            }

            $strong_num_st = $strong_pos + 8;
            $strong_num_en = strpos($attr, '"', $strong_num_st) - 1;
            $strong_num = substr($attr, $strong_num_st, $strong_num_en - $strong_num_st + 1);

            return $word . '{' . $strong_num . '}';
        }, $text);

        return $text;
    }

    protected function _preFormatText($text) 
    {
        $text = parent::_preFormatText($text);
        $text = preg_replace('/\s+/', ' ', $text);
        return $text;
    }

    protected function _postFormatText($text) 
    {
        // Remove unsupported special content
        $remove_contents = [
            'f',    // footnotes
            'ef',   // extended footnotes
            'ex',   // extended cross references
            'va',   // Alternate verse number
            'rq',   // Inline quotation reference(s).
            'x',    // Cross references
        ];

        // We KEEP vp - published verse number
        $text = str_replace("\+", "\\", $text);

        foreach($remove_contents as $c) {
            $pattern = "/\\\\$c (.+?)\\\\$c\*/";
            $text = preg_replace($pattern, '', $text);
        }

        // Remove USFM 3 milestone markers incl. word-alignment (\zaln-s|…\*, \zaln-e\*, \qt-s\*)
        $text = preg_replace('/\\\\[a-z0-9]+-[se][^\\\\]*\\\\\*/i', '', $text);

        // // Remove any other formatting markup
        $text = preg_replace('/\\\\[a-z][a-z0-9-]*\*?/i', '', $text);
        $text = str_replace('\*', '', $text); // stray milestone / marker closers

        /// ??? what was this for?  Came from pre-existing IRV iporter
        if(preg_match('/[0-9]+:[0-9]+/', $text)) {
            $lpp = strrpos($text, '(');

            if($lpp !== false) {
                $text = substr($text, 0, $lpp);
            }
        }
        
        // Check to see if we got everything
        // comment out or remove in production
        // if(strpos($text, '\\') !== false) {
        //     die('BAD FORMAT: ' . $text);
        // }

        return parent::_postFormatText($text);
    }

    protected function _removeUnusedTags($text)
    {
        $text = parent::_removeUnusedTags($text);
        return $text;
    }
}
