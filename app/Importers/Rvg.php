<?php

namespace App\Importers;
use App\Models\Bible;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??

/*
 * New RVG importer
 *
 * Incoming markup / meta is as follows:
 *  [,] - italics
 *  <,> - red letter
 *  «,» - Chapter titles in Psalms
 *
 */

//[brackets] are for Italicized words
//
//<brackets> are for the Words of Christ in Red
//
//«brackets»  are for the Titles in the Book  of Psalms.

class Rvg extends ImporterAbstract 
{
    protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '[';
    protected $italics_en   = ']';
    protected $redletter_st = '<';
    protected $redletter_en = '>';
    protected $strongs_st   = NULL;
    protected $strongs_en   = NULL;
    protected $paragraph    = NULL;

    protected function _importHelper(Bible &$Bible): bool  
    {
        ini_set("memory_limit", "500M");

        // Script settings
        $dir  = dirname(__FILE__) . '/../../bibles/misc/'; // directory of Bible files
        $file   = 'RVG23-RLI-20251028.txt';
        $path   = $dir . $file;
        $module = $this->module;

        // Where did you get this Bible?
        $source = "";

        $insert_into_bible_table    = TRUE; // Inserts (or updates) the record in the Bible versions table
        $overwrite_existing         = $this->overwrite;

        $Bible    = $this->_getBible($module);
        $existing = $this->_existing;

        if(!$overwrite_existing && $this->_existing) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        $contents = file($path);

        if(!$contents) {
            return $this->addError('Unable to open ' . $file, 4);
        }

        if(count($contents) != 31102) {
            return $this->addError('Doesnt have 31102 lines');
        }

        if($this->insert_into_bible_table) {
            $attr = $this->bible_attributes;
//            $attr['description'] = $desc . '<br /><br />' . $source;
            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);
        $Verses = $Bible->verses();
        $table  = $Verses->getTable();

        if(\App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $map = DB::table('verses_kjv')->select('id', 'book', 'chapter', 'verse')->get();

        if(count($map) != 31102) {
            return $this->addError('KJV Map Doesnt have 31102 lines');
        }

        foreach($contents as $key => $text) {
            $mapped = $map[$key];

            // <> indicate red letter. Removing for now as it will screw up display in HTML
            // $text = str_replace(array('<', '>'), '', $text);
            $this->_addVerse($mapped->book, $mapped->chapter, $mapped->verse, $text);
        }

        $this->_insertVerses();
        return true;
    }

    public function checkUploadedFile(\Illuminate\Http\UploadedFile $File): bool  
    {
        return $this->addError('Not implemented for RVG importer', 4);
    }
}
