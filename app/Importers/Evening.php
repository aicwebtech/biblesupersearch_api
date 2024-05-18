<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use SQLite3;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

/**
 * Imports Bibles in the eveningdew format
 *
 *  *Incoming meta:
 *  [,] - italics
 *  {,} - strongs
 */

class Evening extends ImporterAbstract 
{
    protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '[';
    protected $italics_en   = ']';
    protected $redletter_st = NULL;
    protected $redletter_en = NULL;
    protected $strongs_st   = '{';
    protected $strongs_en   = '}';
    protected $paragraph    = NULL;
    protected $path_short   = 'evening';
    protected $file_extensions = ['.dat'];

    protected function _importHelper(Bible &$Bible): bool  
    {
        ini_set("memory_limit", "50M");

        // Script settings
        // $dir    = dirname(__FILE__) . '/../../bibles/evening/'; // directory of Bible files
        $dir = $this->getImportDir();
        $file   = $this->file;   // File name, minus extension
        $module = $this->module; // Module and db name

        // Where did you get this Bible?
        $source = "This Bible imported from EveningDew Bible";

        // Advanced options (Hardcoded for now)
        $testaments = 'both';
        $insert_into_bible_table    = TRUE; // Inserts (or updates) the record in the Bible versions table
        $overwrite_existing         = $this->overwrite;

        // $Bible   = $this->_getBible($module);
        $dirpath = $dir . $file;

        if(!$overwrite_existing && $this->_existing) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if(!is_dir($dirpath)) {
            return $this->addError('Folder does not exist: ' . $file . ' ' . $dirpath);
        }

        $nt  = file($dirpath . "/NewTest/map.dat");
        $ot  = file($dirpath . "/OldTest/map.dat");
        $all = array_merge($ot, $nt);

        if($insert_into_bible_table) {
            $attr = $this->bible_attributes;
            $attr['description'] = $source;

            // These retentions should be removed once V2 tables fully imported
            // $retain = ['lang', 'lang_short', 'shortname', 'name'];

            // foreach($retain as $item) {
            //     if(!empty($Bible->$item)) {
            //         unset($attr[$item]);
            //     }
            // }

            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);

        if(\App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $this->_installHelper($dirpath, $ot, 'OldTest');
        $this->_installHelper($dirpath, $nt, 'NewTest');
        return TRUE;
    }

    private function _installHelper($dir, $test, $sub) {
        $bnum  = (($sub == "newTest") || ($sub == "NewTest")) ? 39 : 0;
        $debug = FALSE;

        foreach($test as $book) {
            $bnum ++;
            $b = explode(",", $book);
            $file = file($dir . "/" . $sub . "/" . $b[0] . ".dat");

            $index = 0;
            $stop  = 100;
            $chap  = 0;

            foreach($file as $line) {
                if(substr($line,0,7) == "Chapter") {
                    $chap += 1;
                }
                else {
                    $sp = strpos($line, " ");

                    if ($sp !== FALSE){
                        $verse = substr($line, 0, $sp);
                        $text  = substr($line, $sp);
                        //$text = str_replace("'", "\'", $text);
                        //$text=str_replace("  ", " ", $text);
                        $this->_addVerse($bnum, $chap, $verse, $text);
                    }
                }

                $index ++;

                if(($debug) && ($index > $stop)) {
                    break;
                }
            }

            $this->_insertVerses();
            return TRUE;
        }
    }

    public function checkUploadedFile(UploadedFile $File): bool  
    {
        return TRUE;
    }
}
