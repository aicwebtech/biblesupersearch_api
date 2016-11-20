<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Importers;

/**
 * Imports Bibles in the Unbound Bible format
 *  
 * Converts Bibles in a single file (as opposed to one book/file) to MySQL for use on Bible SuperSearch.
 * will convert single-file Bibles in both formats avaliable on http://unbound.biola.edu/
 * 
 * Files can be in one of the following two formats
 *      book_index<tab>chapter<tab>verse<tab>text
 *      EX: 01	1	1	In the beginning God created the heaven and the earth.
        // or
        // book_index<tab>chapter<tab>verse<tab><tab>subverse(ignored)<tab>text
        // Ex: 01	1	1		10	In the beginning God created the heaven and the earth.
        //
        // Where book_index is the number of the book, Genesis = 1, Revelation = 66.   Subverse is ignored.
        //
        //
        // Many Bibles in these formats are avaiable for download on "The Unbound Bible" download site.
        // You can use either the old or the new format avaiable.
        // http://unbound.biola.edu/
        // To use one of these Bibles, download the compressed file, and extract the files
        // <bible>_utf8.txt and <bible>.html, where <bible> is the name of the Bible. 

 */
class Unbound {
    //put your code here
    
    public function import() {
        ini_set("memory_limit","30000000");



        // script options

        $dir=""; // directory of Bible files
        $file="kjv_apocrypha";//File name, minus "_utf8.txt" example: "kjv_utft8.tx" => enter "kjv"
        //$shortname="";
        $bible="kjv"; // short name or abbreviation
        $name="Authorized King James Version"; // full name, to appear in the Bible version menu
        $language="en"; // 2-3 character language code
        $language_long="English";  // full English name of the language 
        //Which testaments does this Bible contain: ot,nt,both
        $testaments="both"; 
        // Where did you get this Bible?   
        $source="This Bible imported from The Unbound Bible <a href='http://unbound.biola.edu/'>http://unbound.biola.edu/</a>";

        // Advanced options
        $book_list=true;
        $install_bible=true;
        $insert_into_bible_table=true;

        // end options

        require_once("bible_mysql.php");
        connect();

        // get form options, if submitted
        if ($_POST["submit"]=="true") {

        $file=mysan($_POST["file"]);
        $bible=mysan($_POST["bible"]);
        $name=mysan($_POST["name"]);
        $language=mysan($_POST["language"]);
        $language_long=mysan($_POST["language_long"]);
        $testaments=mysan($_POST["testaments"]);
        $source=mysan($_POST["source"]);

        if($_POST["book_list"]=="yes"){$book_list=true;}
        else{$book_list=false;}
        if($_POST["install_bible"]=="no"){$install_bible=false;}
        else{$install_bible=true;}
        if($_POST["insert_into_bible_table"]=="no"){$insert_into_bible_table=false;}
        else{$insert_into_bible_table=true;}

        //print_r($_POST);

        if(($bible=="")||($name=="")|($file=="")|($language=="")|($language_long=="")|($testaments=="")|($source=="")) {
                echo("YOU DIDN'T FILL IN ALL FIELDS. ALL FIELDS ARE REQUIRED.<BR><br>");

                require("bible_add_interface.php");
                return;
        }// end if

        }// end post



        // book list
        if(($book_list)&&($list=file($language.".txt"))){

        if ($book_list==true){
        // extract book list

        $su="DROP TABLE IF EXISTS `bible_books_$language`;";
        $su2="CREATE TABLE `bible_books_$language` (
          `number` int(11) NOT NULL auto_increment,
          `fullname` tinytext NOT NULL,
          `short` tinytext NULL,
          `chapters` int(11) NOT NULL,
          PRIMARY KEY  (`number`)
        ) ENGINE=MyISAM PACK_KEYS=0 AUTO_INCREMENT=1 ;";

        }// end if

        mysql_query($su);
        echo(mysql_error());
        mysql_query($su2);
        echo(mysql_error());

        //if($list=file($language.".txt")){

        if($testaments=="nt"){$st=40;}
        else{$st=1;}

        foreach($list as $book){

        $bo=explode("	",$book);
        $book=mb_convert_case(mb_strtolower($bo[0],"utf-8"),MB_CASE_TITLE,"utf-8");

        echo (" &nbsp; $book #$st<br>");
        $qu="INSERT INTO `bible_books_$language` ( `number` , `fullname` , `short` , `chapters` ) VALUES (NULL , '$book', '', ''
        );";

        mysql_query($qu);
        echo(mysql_error());

        $st+=1;
        }// end foreach

        $res=mysql_query("select * from `bible_books_$language");
        while($b=mysql_fetch_array($res)){
        echo($b["fullname"]."<br>");
        }// end while

        }//end if

        if($install_bible) {
            var_dump('installing ' . $bible);

            mysql_query('SET NAMES utf8;');
            mysql_query('SET CHARACTER SET utf8');

            $loc=$file."_utf8.txt";
            if($dir!=""){$loc="$dir/$loc";}

            $bib = file($loc);

            //$bib=explode("\n",$bib);

            $sub=substr($bib[0],0,1);

            if(($sub=="0")|($sub=="4")){$i=0;}
            else{$i=7;}

            $ver=explode("	",$bib[10]);
            if($ver[3]==""){$t=5;}
            else{$t=3;}

            while($ver=$bib[$i]) {

                $ver=explode("	",$ver);

                $book=substr($ver[0],0,2);
                if ($book>66){break;}// omit any heretical books
                if (($book>39)&&($testaments=="ot")){break;}

                $chapter=$ver[1];
                $verse=$ver[2];
                //$text=str_replace("'","\'",$ver[$t]);
                $text = mysql_real_escape_string(trim($ver[$t]));

                $qu="insert into `bible_$bible` values(NULL, '$book', '$chapter', '$verse', '$text')";
                if($book>0){mysql_query($qu);}

                //echo(mysql_error()." $book $chapter:$verse<br>");

                $i++;

                if($i > 100) {
                  //break;
                }

                //while(substr($bib[$i],0,1)=="#"){$i++;}

                //if($i==21){break;}

            }// end while

        }// end if

    }
}
