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

/**
 * Imports Bibles in the eveningdew format
 *  Todo - build this!
 */

class Evening extends ImporterAbstract {
    protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    public function import() {
        ini_set("memory_limit", "50M");

        // Script settings
        $dir  = dirname(__FILE__) . '/../../bibles/analyzer/'; // directory of Bible files
        $file   = $this->file;   // File name, minus extension
        $module = $this->module; // Module and db name

        // Where did you get this Bible?
        $source = "This Bible imported from Bible Analyzer <a href='http://www.bibleanalyzer.com/download.htm'>http://www.bibleanalyzer.com/download.htm</a>";

        // Advanced options (Hardcoded for now)
        $testaments = 'both';
        $insert_into_bible_table    = TRUE; // Inserts (or updates) the record in the Bible versions table
        $overwrite_existing         = $this->overwrite;

        $Bible    = Bible::findByModule($module);
        $existing = ($Bible) ? TRUE   : FALSE;
        $Bible    = ($Bible) ? $Bible : new Bible;
        $filepath = $dir . $file;

        if(!$overwrite_existing && $existing) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($existing) {
            $Bible->uninstall();
        }

        if(!is_file($filepath)) {
            return $this->addError('File does not exist: ' . $file);
        }

        $SQLITE = new SQLite3($filepath);

        $res_desc = $SQLITE->query('SELECT * FROM title');
        $info = $res_desc->fetchArray(SQLITE3_ASSOC);
        $desc = $info['info'];
//        var_dump($info);

        if($insert_into_bible_table) {
            $attr = $this->bible_attributes;
            $attr['description'] = $desc . '<br /><br />' . $source;

            // These retentions should be removed once V2 tables fully imported
            $retain = ['lang', 'lang_short', 'shortname', 'name'];

            foreach($retain as $item) {
                if(!empty($Bible->$item)) {
                    unset($attr[$item]);
                }
            }

            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);
        $Verses = $Bible->verses();
        $table  = $Verses->getTable();
        $st = ($testaments == 'nt') ? 40 : 0;

        if(\App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $res_bib = $SQLITE->query('SELECT * FROM bible');
        $book = $i = 0;
        $last_book_name = NULL;

        while($row = $res_bib->fetchArray(SQLITE3_ASSOC)) {
            $ref_arr = explode(' ', $row['ref']);

            if($ref_arr[0] != $last_book_name) {
                $book ++;
                $last_book_name = $ref_arr[0];
            }

            if ($book > 66) {
                break; // Omit any heretical books
            }

            list($chapter, $verse) = explode(':', $ref_arr[1]);

            $chapter = intval($chapter);
            $verse   = intval($verse);
            $text    = trim($row['verse']);

            if(!$book || !$chapter || !$verse) {
                continue;
            }

            $binddata = array(
                'book'             => $book,
                'chapter'          => $chapter,
                'verse'            => $verse,
                'chapter_verse'    => $chapter * 1000 + $verse,
                'text'             => $text,
            );

            DB::table($table)->insert($binddata);
            $i++;

            if($i > 100) {
//                break;
            }
        }


    }

    private function _installHelper($test, $sub) {
        global $dir, $bible,$debug;

        if (($sub=="newTest")||($sub=="NewTest")){$bnum=39;}
        else{$bnum=0;}

        foreach($test as $book) {

            $bnum++;
            $b=explode(",",$book);

            $file=file($dir."/".$sub."/".$b[0].".dat");

            $index=0;
            $stop=100;

            $chap=0;

            foreach($file as $line){

                if(substr($line,0,7)=="Chapter"){
                    $chap+=1;
                }// end if
                else{
                    $sp=strpos($line," ");
                    if ($sp!==false){
                        // insert verse

                        $verse=substr($line,0,$sp);
                        $text=substr($line,$sp);

                        $text=fix_spelling($text);

                        // map out strongs
                        $st_map=mapstrongs($text);

                        $text=$st_map['text'];
                        $strongs=$st_map['map'];

                        // map out italics
                        $st_map=mapverse($text);

                        $text=$st_map['text'];
                        $italics=$st_map['map'];

                        //

                        $text=str_replace("'","\'",$text);
                        //$text=str_replace("  "," ",$text);
                        $text=trim($text);

                        $qu="insert into `bible_$bible` values(NULL, '$bnum', '$chap', '$verse', '$text','$italics','$strongs')";

                        mysql_query($qu);
                        echo(mysql_error());


                    }// end if


                }// end else

                $index++;

                if(($debug)&&($index>$stop)){
                    break;

                }

            }// end foreach


        }
    }
}
