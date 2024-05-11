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

    protected function _importHelper(Bible &$Bible) 
    {
        ini_set("memory_limit", "500M");

        $dir    = $this->getImportDir();
        $file   = $this->file;   // File name, minus extension
        $module = $this->module; // Module and db name
        $module = $this->module;
        $attr = $this->bible_attributes;

        if($this->debug) {
            $file = 'eng-kjv2006_usfm.zip';
            $module = 'usfm_' . time();
            $attr['lang_short'] = 'en';
            $attr['lang'] = 'English';
        }

        $zipfile = $dir . $file;

        // Where did you get this Bible?
        $source = "";

        $overwrite_existing  = $this->overwrite;

        $Bible    = $this->_getBible($module);
        $existing = $this->_existing;

        if(!$overwrite_existing && $this->_existing) {
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
    }

    private function _zipImportHelper(&$Zip, $filename) 
    {
        $pseudo_book = intval($filename);
        $chapter = $verse = NULL;

        if(!$pseudo_book) {
            return FALSE;
        }

        if($pseudo_book < 70) {
            $book = $pseudo_book - 1;
        }
        else {
            $book = $pseudo_book - 30;
        }

        $next_line_para = FALSE;
        $bib = $Zip->getFromName($filename);
        $bib = preg_split("/\\r\\n|\\r|\\n/", $bib);

        foreach($bib as $line) {

            if(strpos($line, '\c') === 0) {
                preg_match('/([0-9]+)/', $line, $matches);
                $chapter = (int) $matches[1];
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

            if(preg_match('/[0-9]+:[0-9]+/', $text)) {
                $lpp = strrpos($text, '(');
                $text = substr($text, 0, $lpp);
            }

            if($next_line_para) {
                $text = '¶ ' . $text;
                $next_line_para = FALSE;
            }

            // $text = str_replace('*', '', $text);

            // if($book == 43 && $chapter == 3 && $verse == 3) {
                // var_dump($text);
                $this->_addVerse($book, $chapter, $verse, $text, TRUE);
            // }

            // if($verse == 4) {die('dead');}
        }

        return TRUE;
    }

    public function checkUploadedFile(UploadedFile $File) 
    {
        return TRUE;
    }

    protected function _formatStrongs($text)
    {
        //\w beginning|strong="H7225"\w*

        // var_dump($text);

        // Currently included:
        // \p{L}: any kind of letter from any language.
        // \p{M}: a character intended to be combined with another character (e.g. accents, umlauts, enclosing boxes, etc.).
        // \p{N}: any kind of numeric character in any script.

        // Other punctuation: 
        // \p{P}: any kind of punctuation character.
        // \p{Pd}: any kind of hyphen or dash.
        // \p{Ps}: any kind of opening bracket.
        // \p{Pe}: any kind of closing bracket.
        // \p{Pi}: any kind of opening quote.
        // \p{Pf}: any kind of closing quote.
        // \p{Po}: Other:  any kind of punctuation character that is not a dash, bracket, quote
        $repeater_1 = '\p{L}\p{M}';
        // $repeater_2 = '\p{L}\p{M}0-9="';
        $repeater_2 = '.';

        $text = str_replace('\+w', '\w', $text);
        $text = str_replace('\+w*', '\w*', $text);

        // \v 3  \w Jesus|strong="G2424"\w* \w answered|strong="G0611"\w* \w and|strong="G2532"\w* \w said|strong="G2036"\w* unto \w him|strong="G0846"\w*, \wj  \+w Verily|strong="G0281"\+w*, \+w verily|strong="G0281"\+w*, \+w I say|strong="G3004"\+w* \+w unto thee|strong="G4671"\+w*, \+w Except|strong="G3361"\+w* \+w a man|strong="G5100"\+w* \+w be born|strong="G1080"\+w* \+w again|strong="G0509"\+w*, he \+w can|strong="G1410"\+w*\+w not|strong="G3756"\+w* \+w see|strong="G1492"\+w* \+w the kingdom|strong="G0932"\+w* \+w of God|strong="G2316"\+w*.\wj* 

        // custom strongs handling here
        // $pattern = "/\\\w ([" . $repeater_1 . "]+?)\|([" . $repeater_2 . "]+?)\\\w\*/";
        $pattern = "/\\\w (.+?)\\\w\*/"; // works for non-red words
        // $pattern = "/\\\+w (.+?)\\\+w\*/";
        // $pattern = "/wwww(.*)/";

        $text = preg_replace_callback($pattern, function($matches) {
            // Note: strong is the only word attribute we use, we discard all others!
            list($word, $attr) = explode('|', $matches[1]);

            // print_r($matches);
            // var_dump($word);
            // var_dump($attr);

            $strong_pos = strpos($attr, 'strong');

            if($strong_pos === false) {
                return $word;
            }

            $strong_num_st = $strong_pos + 8;
            $strong_num_en = strpos($attr, '"', $strong_num_st) - 1;

            $strong_num = substr($attr, $strong_num_st, $strong_num_en - $strong_num_st + 1);

            // var_dump($strong_num);
            return $word . '{' . $strong_num . '}';
        }, $text);

        // Strip these tags AND content

        // Strip these tags, retain content

        // die('wonkey');
        // \v 4 And \w God|strong="H0430"\w* \w saw|strong="H7200"\w* the \w light|strong="H0216"\w*, \w that|strong="H3588"\w* \add it was\add* \w good|strong="H2896"\w*: and \w God|strong="H0430"\w* \w divided|strong="H0914"\w* the \w light|strong="H0216"\w* \w from|strong="H0996"\w* the \w darkness|strong="H2822"\w*.\f + \fr 1.4  \ft the light from…: Heb. between the light and between the darkness\f* 

        // \w God|strong="H0430"\w*
        // \w saw|strong="H7200"\w*
        // \w light|strong="H0216"\w*
        // \w that|strong="H3588"\w*
        // \w good|strong="H2896"\w*
        // \w God|strong="H0430"\w*
        // \w divided|strong="H0914"\w*
        // \w light|strong="H0216"\w*
        // \w from|strong="H0996"\w*
        // \w darkness|strong="H2822"\w*

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
        $remove_contents = [
            'f'
        ];

        // var_dump($text);

        foreach($remove_contents as $c) {
            $pattern = "/\\\\$c (.+?)\\\\$c\*/";
            $text = preg_replace($pattern, '', $text);
        }

        // var_dump($text);

        return parent::_postFormatText($text);
    }

    protected function _removeUnusedTags($text)
    {
        $text = parent::_removeUnusedTags($text);
        return $text;
    }
}
