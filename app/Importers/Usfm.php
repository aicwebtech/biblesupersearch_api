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

    protected function _importHelper(Bible &$Bible): bool
    {
        ini_set("memory_limit", "500M");

        $dir    = $this->getImportDir();
        $file   = $this->file;   // File name, minus extension
        $module = $this->module; // Module and db name
        $attr   = $this->bible_attributes;

        if($this->debug) {
            // $file = 'eng-kjv2006_usfm.zip';
            // $file = 'eng-kjv_usfm_apoc.zip';
            $file = 'engwebu_usfm.zip';
            $module = 'usfm_' . time();
            $attr['lang_short'] = 'en';
            $attr['lang'] = 'English';
            $Bible = $this->_getBible($module);
        }

        $zipfile = $dir . $file;

        // Where did you get this Bible?
        $source = "";

        $overwrite_existing  = $this->overwrite;

        $existing = $this->_existing;

        if(!$overwrite_existing && $this->_existing && $this->insert_into_bible_table) {
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
                // $attr['description'] = $desc . '<br /><br />' . $source;
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

    private function _zipImportHelper(&$Zip, $filename) 
    {
        $pseudo_book = intval($filename);
        $chapter = $verse = NULL;

        if(!$pseudo_book) {
            return FALSE;
        }

        if($pseudo_book >= 2 && $pseudo_book <= 40) {
            // Old Testament book
            $book = $pseudo_book - 1;
        } else if($pseudo_book >= 70) {
            // New Testament book
            $book = $pseudo_book - 30;
        } else {
            // Apocryphal book, not supported
            return false;
        }

        $next_line_para = FALSE;
        $bib = $Zip->getFromName($filename);
        $bib = preg_split("/\\r\\n|\\r|\\n/", $bib);

        foreach($bib as $line) {

            if(strpos($line, '\c') === 0) {
                if(preg_match('/([0-9]+)/', $line, $matches)) {
                    $chapter = (int) $matches[1];
                }

                continue;
            }            
            if(strpos($line, '\p') === 0) {
                $next_line_para = TRUE;
                continue;
            }

            if(strpos($line, '\v') === FALSE) {
                continue;
            }

            preg_match('/([0-9]+) (.+)/', $line, $matches);
            $verse = (int) $matches[1];
            $text  = $matches[2];

            // Moved
            // if(preg_match('/[0-9]+:[0-9]+/', $text)) {
            //     $lpp = strrpos($text, '(');
            //     $text = substr($text, 0, $lpp);
            // }

            if($next_line_para) {
                $text = '¶ ' . $text;
                $next_line_para = FALSE;
            }

            $this->_addVerse($book, $chapter, $verse, $text, true);
        }

        return true;
    }

    public function checkUploadedFile(UploadedFile $File): bool 
    {
        
        $zipfile    = $File->getPathname();
        $file       = static::sanitizeFileName( $File->getClientOriginalName() );
        $Zip        = new ZipArchive();

        if(stripos($file, 'usfm') === false) {
            return $this->addError('Does not appear to be a USFM file; filename does not contain "usfm".');
        }

        $allowed = [
            'copr.htm',
            'keys.asc',
            'signature.txt.asc',
        ];

        if($Zip->open($zipfile) == true) {
            for ($i = 0; $i < $Zip->numFiles; $i++) {
                $filename = $Zip->getNameIndex($i);
                $spl = explode('.', $filename);
                $ext = array_pop($spl);

                if(!in_array($filename, $allowed) && $ext != 'usfm' && $ext != 'css') {
                    return $this->addError('ZIP file contains a file with a non-standard extension: ' . $filename);
                }
            }
        }

        return true;
    }

    protected function _formatStrongs($text)
    {
        // Currently included:
        // \p{L}: any kind of letter from any language.
        // \p{M}: a character intended to be combined with another character (e.g. accents, umlauts, enclosing boxes, etc.).
        // \p{N}: any kind of numeric character in any script.

        $repeater_1 = '\p{L}\p{M}';
        // $repeater_2 = '\p{L}\p{M}0-9="';
        $repeater_2 = '.';

        // clean up to handle strongs within red-letter words
        $text = str_replace('\+w', '\w', $text); 
        $text = str_replace('\+w*', '\w*', $text);

        // custom strongs handling here
        $pattern = "/\\\w (.+?)\\\w\*/"; // works for non-red words

        $text = preg_replace_callback($pattern, function($matches) {
            // Note: strong is the only word attribute we use, we discard all others!
            list($word, $attr) = explode('|', $matches[1]);

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
        ];

        $text = str_replace("\+", "\\", $text);

        foreach($remove_contents as $c) {
            $pattern = "/\\\\$c (.+?)\\\\$c\*/";
            $text = preg_replace($pattern, '', $text);
        }

        // Remove any other formatting
        $text = preg_replace('/\\\\[a-z]+\*?/', '', $text);

        if(preg_match('/[0-9]+:[0-9]+/', $text)) {
            $lpp = strrpos($text, '(');
            $text = substr($text, 0, $lpp);
        }
        
        // Check to see if we got everything
        // comment out or remove in production
        if(strpos($text, '\\') !== false) {
            die('BAD FORMAT: ' . $text);
        }

        return parent::_postFormatText($text);
    }

    protected function _removeUnusedTags($text)
    {
        $text = parent::_removeUnusedTags($text);
        return $text;
    }
}
