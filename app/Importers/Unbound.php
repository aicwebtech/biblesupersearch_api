<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use \DB; 
use Illuminate\Http\UploadedFile;

/**
 * Imports Bibles in the Unbound Bible format
 *
 * Converts Bibles in a single file (as opposed to one book/file) to MySQL for use on Bible SuperSearch.
 * will convert single-file Bibles in both formats avaliable on http://unbound.biola.edu/
 *
 * Files can be in one of the following two formats
 *      book_index<tab>chapter<tab>verse<tab>text
 *      EX: 01	1	1	In the beginning God created the heaven and the earth.
 *      or
 *      book_index<tab>chapter<tab>verse<tab><tab>subverse(ignored)<tab>text
 *      Ex: 01	1	1		10	In the beginning God created the heaven and the earth.
 *
 *      Where book_index is the number of the book, Genesis = 1, Revelation = 66.   Subverse is ignored.
 *
 *
 *      Many Bibles in these formats are avaiable for download on "The Unbound Bible" download site.
 *      You can use either the old or the new format avaiable.
 *      http://unbound.biola.edu/
 *      To use one of these Bibles, download it's zip file and place in <document root>/bibles/unbound
 */

class Unbound extends ImporterAbstract {
    // protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '[';
    protected $italics_en   = ']';
    protected $redletter_st = NULL;
    protected $redletter_en = NULL;
    protected $strongs_st   = NULL;
    protected $strongs_en   = NULL;
    protected $paragraph    = '¶ ';
    protected $path_short   = 'unbound';
    protected $file_extensions = ['.zip'];
    protected $source = "This Bible imported from The Unbound Bible <a href='http://unbound.biola.edu/'>http://unbound.biola.edu/</a>"; // Where did you get this Bible?

    protected $has_gui = TRUE;

    protected function _importHelper(Bible &$Bible) {
        if(config('app.env') != 'testing') {
            ini_set("memory_limit", "50M");
        }

        // Script settings
        $dir    = $this->getImportDir();
        $file   = $this->file;   // File name, minus extension
        $module = $this->module; // Module and db name

        // Which testaments does this Bible contain: ot, nt,both
        $testaments = "both";

        // To do - move this logic!
        $zipfile  = $dir . $file;
        $Zip      = new ZipArchive();

        if(!$this->overwrite && $this->_existing && $this->insert_into_bible_table) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if($Zip->open($zipfile) === TRUE) {
            $file_raw  = substr($file, 0, strlen($file) - 4);
            $txt_file  = $file_raw . "_utf8.txt";
            $desc_file = $file_raw . ".html";
            $bib  = $Zip->getFromName($txt_file);
            $desc = $Zip->getFromName($desc_file);

            if(preg_match('/<body>(.*?)<\/body>/', $desc, $matches) == 1) {
                $desc = $matches[1];
            }

            $Zip->close();
        }
        else {
            return $this->addError('Unable to open ' . $zipfile, 4);
        }

        if($this->insert_into_bible_table) {
            $attr = $this->_parseAttributes($desc);
            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);
        // $Verses = $Bible->verses();
        $table  = $this->_table;
        $st = ($testaments == 'nt') ? 40 : 0;

        if(config('app.env') != 'testing' && \App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $bib = preg_split("/\\r\\n|\\r|\\n/", $bib);
        $sub = substr($bib[0], 0, 1);
        $i = (($sub == "0") | ($sub == "4")) ? 0 : 7;

        $ver = explode("	", $bib[10]);
        $t = (!$ver[3]) ? 5 : 3;

        while($ver = $bib[$i]) {
            $ver = explode("	", $ver);
            $book = substr($ver[0], 0, 2);

            if ($book > 66) {
                break; // Omit any heretical books
            }

            if (($book > 39) && ($testaments == "ot")) {
                break;
            }

            $chapter = intval($ver[1]);
            $verse   = intval($ver[2]);
            $text    = trim($ver[$t]);

            if(!$book || !$chapter || !$verse) {
                $i++;
                continue;
            }

            $this->_addVerse($book, $chapter, $verse, $text, TRUE);

            $i++;

            if($i > 100) {
              //break;
            }
        }

        $this->_insertVerses();
        return TRUE;
    }

    protected function _preFormatText($text) {
        $text = parent::_preFormatText($text);
        $text = str_replace(['‹‹', '››'], '"', $text);
        $text = str_replace(['‹', '›'], '\'', $text);
        return $text;
    }

    private function _parseAttributes($desc, $clear_existing = FALSE) {
        $attr = $this->bible_attributes ?: [];
        $attr = $clear_existing ? [] : $attr;
        $lang = NULL;
        $name = NULL;
        
        // Attempt to parse name and language from Unbound description
        if(preg_match('/<b>(.*?)<\/b>/', $desc, $matches) == 1) {
            $lang_name = explode(': ', $matches[1]);

            if(count($lang_name) == 1) {
                $name = $lang = $lang_name[0];
            }
            else {
                $lang = $lang_name[0];
                $name = $lang_name[1];
            }

            $attr['name'] = (empty($attr['name'])) ? $name : $attr['name'];
            $attr['shortname'] = $attr['name'];
        }

        $attr['description'] = $desc . '<br /><br />' . $this->source;
        
        if(!array_key_exists('lang_short', $attr)) {
            $attr['lang_short']  = static::getLanguageCode($lang);
        }

        $this->bible_attributes = $attr;
        return $attr;
    }

    public function checkUploadedFile(UploadedFile $File) {
        $zipfile = $File->getPathname();

        $file = static::sanitizeFileName( $File->getClientOriginalName() );
        $desc = NULL;
        $file_parts = pathinfo($file);

        $Zip = new ZipArchive();

        if($Zip->open($zipfile) === TRUE) {
            try {
                $file_raw  = substr($file, 0, strlen($file) - 4);
                $txt_file  = $file_raw . "_utf8.txt";
                $desc_file = $file_raw . ".html";
                $bib  = $Zip->getFromName($txt_file);
                $desc = $Zip->getFromName($desc_file);

                if(preg_match('/<body>(.*?)<\/body>/', $desc, $matches) == 1) {
                    $desc = $matches[1];
                }

                $Zip->close();
            }
            catch(\Exception $e) {
                return $this->addError('Could not read zip file: ' . $file);
            }
        }
        else {
            return $this->addError('Unable to open .zip file: ' . $file, 4);
        }

        $this->_parseAttributes($desc, TRUE);
        $this->bible_attributes['module'] = static::generateUniqueModuleName($file_parts['filename']);

        return TRUE;
    }
}
