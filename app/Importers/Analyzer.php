<?php

namespace App\Importers;
use App\Models\Bible;
use App\Models\Books\BookAbstract As Book;
use ZipArchive;
use SQLite3;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

/**
 * Imports Bibles in the Bible Analyzer .bib (SQLite) format
 *
 * Incoming meta:
 *  <i>, </i> - italics
 *  <r>, </r> - red letter
 *  <fn>, </fn> - footnote (remove)
 *  [,] - strongs
 */

class Analyzer extends ImporterAbstract {
    // protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '<i>';
    protected $italics_en   = '</i>';
    protected $redletter_st = '<r>';
    protected $redletter_en = '</r>';
    protected $strongs_st   = '[';
    protected $strongs_en   = ']';
    protected $paragraph    = '¶ ';
    protected $unused_tags  = ['fn', 'a'];
    protected $path_short   = 'analyzer';

    protected $has_gui      = TRUE;

    // Where did you get this Bible?
    protected $source = "This Bible imported from Bible Analyzer <a href='http://www.bibleanalyzer.com/download.htm'>http://www.bibleanalyzer.com/download.htm</a>";

    protected function _importHelper(Bible &$Bible) {
        if(config('app.env') != 'testing') {
            ini_set("memory_limit", "50M");
        }

        // Script settings
        $dir    = $this->getImportDir();
        $file   = $this->file;   
        $module = $this->module; // Module and db name

        // Advanced options (Hardcoded for now)
        $testaments = 'both';

        // $Bible    = $this->_getBible($module);
        $filepath = $dir . $file;

        if(!$this->overwrite && $this->_existing && $this->insert_into_bible_table) {
            return $this->addError('Module already exists: \'' . $module . '\' Use --overwrite to overwrite it.', 4);
        }

        if($this->_existing) {
            $Bible->uninstall();
        }

        if(!is_file($filepath)) {
            return $this->addError('File does not exist: ' . $file);
        }

        $SQLITE = new SQLite3($filepath);

        $res_desc = $SQLITE->query('SELECT * FROM title');
        $info = $res_desc->fetchArray(SQLITE3_ASSOC);
        $desc = $info['info'];

        if($this->insert_into_bible_table) {
            $attr = $this->bible_attributes;
            $attr['description'] = $desc . '<br /><br />' . $this->source;

            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);
        $Verses = $Bible->verses();
        $table  = $Verses->getTable();
        $st = ($testaments == 'nt') ? 40 : 0;

        if(config('app.env') != 'testing' && \App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $res_bib = $SQLITE->query('SELECT * FROM bible ORDER BY id ASC');
        $book = $i = 0;
        $last_book_name = NULL;

        while($row = $res_bib->fetchArray(SQLITE3_ASSOC)) {
            $ref_arr = explode(' ', $row['ref']);

            if($ref_arr[0] != $last_book_name) {
                // Attempt to match book str to English book name
                $Book = Book::findByEnteredName($ref_arr[0], 'en');

                if($Book) {
                    $book = $Book->id;
                } else {
                    $book ++; // fallback - just increment book number
                }

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

            $this->_addVerse($book, $chapter, $verse, $text);
            $i++;

            if($i > 100) {
               // break;
            }
        }

        $this->_insertVerses();
        return TRUE;
    }

    protected function _formatText($text) {
        $text    = $this->_preFormatText($text);
        $text    = $this->_formatStrongs($text);
        $text    = $this->_formatItalics($text);
        $text    = $this->_formatRedLetter($text);
        $text    = $this->_formatParagraph($text);
        $text    = $this->_removeUnusedTags($text);
        $text    = $this->_postFormatText($text);
        return $text;
    }

    public function checkUploadedFile(UploadedFile $File) {
        $path = $File->getPathname();

        try {
            $SQLITE = new SQLite3($path);
            $res_desc = $SQLITE->query('SELECT * FROM title');
            $info = $res_desc->fetchArray(SQLITE3_ASSOC);

            $res_bib = $SQLITE->query('SELECT * FROM bible ORDER BY id ASC LIMIT 10');
            $verse_found = FALSE;
            $book = 0;
            $last_book_name = NULL;
            $desc = iconv("UTF-8","UTF-8//IGNORE", $info['info']);

            $name_parts = explode(',', $info['desc']);

            $this->bible_attributes = [
                'name'          => trim($name_parts[0]),
                'shortname'     => $info['abbr'],
                'module'        => static::generateUniqueModuleName($info['abbr']),
                'description'   => $info['info'] . '<br /><br />' . $this->source,
                'year'          => count($name_parts) > 1 && intval($name_parts[1]) ? trim($name_parts[1]) : NULL,
            ];

            while($row = $res_bib->fetchArray(SQLITE3_ASSOC)) {
                $ref_arr = explode(' ', $row['ref']);

                if($ref_arr[0] != $last_book_name) {
                    $book ++;
                    $last_book_name = $ref_arr[0];
                }

                list($chapter, $verse) = explode(':', $ref_arr[1]);

                $chapter = intval($chapter);
                $verse   = intval($verse);
                $text    = trim($row['verse']);

                if(!$book || !$chapter || !$verse || !$text) {
                    continue;
                }

                $verse_found = TRUE;
                break;
            }
        }
        catch(\Exception $e) {
            return $this->addError('Could not open Bible Analyzer file: ' . $e->getMessage());
        }

        return TRUE;
    }
}
