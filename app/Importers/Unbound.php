<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??

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
    //put your code here
    
    public function import() {
        ini_set("memory_limit", "30M");

        // Script options
        $dir  = dirname(__FILE__) . '/../../bibles/unbound/'; // directory of Bible files
        $file = $this->file; //"kjv_apocrypha"; // File name, minus extension
        $module = $this->module; // Module and db name
        $language = "en"; // 2-3 character language code
        $language_long = "English";  // full English name of the language 
        //
        
        //Which testaments does this Bible contain: ot,nt,both
        $testaments = "both"; 
        // Where did you get this Bible?   
        $source = "This Bible imported from The Unbound Bible <a href='http://unbound.biola.edu/'>http://unbound.biola.edu/</a>";

        // Advanced options
        $install_bible = true;
        $insert_into_bible_table = true;
        $overwrite_existing = TRUE;
        // end options
        
        if($install_bible) {
            $Bible = Bible::where('module', $module)->first();

            if(!$overwrite_existing && $Bible) {
                die('Cannot overwrite ' . $module);
            }
            
            $existing = ($Bible) ? TRUE   : FALSE;
            $Bible    = ($Bible) ? $Bible : new Bible;
            
            if($existing) {
                $Bible->uninstall();
            }
            
            $zipfile = $dir . $file . '.zip';
            $Zip = new ZipArchive();
            
            if($Zip->open($zipfile) === TRUE) {
                $txt_file = $file . "_utf8.txt";
                $desc_file = $file . ".html";
                $bib = $Zip->getFromName($txt_file);
                //$desc = $Zip->getFromName($desc_file);
                $Zip->close();
            }
            else {
                die('Unable to open ' . $zipfile);
            }
            
            //var_dump($this->bible_attributes);
            
            //$Bible->setAttributes($this->bible_attributes);
            
            //var_dump($Bible);
            
            if($existing) {  
                $Bible->save( $this->bible_attributes );
            }
            else {
                $Bible = Bible::forceCreate($this->bible_attributes);
            }
            
            $Bible->install(TRUE);
            $Verses = $Bible->verses();
            $table  = $Verses->getTable();
            $st = ($testaments == 'nt') ? 40 : 0;
            
            echo('installing ' . $module);

            //mysql_query('SET NAMES utf8;');
            //mysql_query('SET CHARACTER SET utf8;');

            //$loc = $file . "_utf8.txt";
            //$loc = ($dir) ? $loc = "$dir/$loc" : $loc;
            //$bib = file($loc);
            
            $bib = preg_split("/\\r\\n|\\r|\\n/", $bib);

            //$bib=explode("\n",$bib);

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
                
                $binddata = array(
                    'book'             => $book,
                    'chapter'          => $chapter,
                    'verse'            => $verse,
                    'chapter_verse'    => $chapter * 1000 + $verse,
                    'text'             => $text,
                );
                
                //$Verses->forceCreate($binddata);
                DB::table($table)->insert($binddata);

                $i++;

                if($i > 100) {
                  //break;
                }

                //while(substr($bib[$i], 0, 1) == "#"){ $i++; }

                //if($i==21){break;}

            }

        }
    }
}
