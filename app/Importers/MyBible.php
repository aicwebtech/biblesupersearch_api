<?php

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use SQLite3;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

/**
 * Imports Bibles in the MyBible .SQLite3 (SQLite) format
 * (Note: Do not confuse with the MySword .mybible format)
 *
 * TODO: This importer needs to be able to correctly handle compressed modules (.gz and .zip)
 *
 */

class MySword extends ImporterAbstract {
    // protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '<i>';
    protected $italics_en   = '</i>';
    protected $redletter_st = '<J>';
    protected $redletter_en = '</J>';
    protected $strongs_st   = '<S>';
    protected $strongs_en   = '</S>';
    protected $paragraph    = '<pb/>'; // TODO - properly handle paragraphs!
    protected $paragraph_at_verse_end = FALSE;
    protected $unused_tags  = ['RF', 'RX']; // These tags, along with text enclosed betwween them, will be removed. RF = Translators' notes, RX = Cross references
    protected $path_short   = 'mybible';

    protected $has_gui      = TRUE;
    protected $has_cli      = FALSE;

    protected $attribute_map = [
            'name'          => 'Description',
            'shortname'     => 'Abbreviation',
            'module'        => 'Abbreviation',
            'description'   => 'Comments',
            'year'          => 'PublishDate',
            'lang_short'    => 'Language',
    ];    

    protected $attribute_map_alt = [
            'name'          => 'description',
            'shortname'     => 'abbreviation',
            'module'        => 'abbreviation',
            'description'   => 'Comments',
            'year'          => 'publishdate',
            'lang_short'    => 'language',
    ];

    // Where did you get this Bible?
    protected $source = "This Bible imported from MyBible";

    protected function _importHelper(Bible &$Bible) {
        ini_set("memory_limit", "50M");

        // Script settings
        $dir    = $this->getImportDir();
        $file   = $this->file;   
        $module = $this->module; // Module and db name

        // Advanced options (Hardcoded for now)
        $testaments = 'both';

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

        if($this->insert_into_bible_table) {
            $info = $this->_getMeta($SQLITE);
            $desc = $info['description'];
            $attr = $this->bible_attributes;
            $attr['description'] = $desc . '<br /><br />' . $this->source;

            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);
        $Verses = $Bible->verses();
        $table  = $Verses->getTable();

        if(\App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $res_bib = $SQLITE->query('SELECT book_number, chapter, verse, text FROM Bible ORDER BY Book ASC, Chapter ASC, Verse ASC');
        
        $i = $cur_book = $last_book = 0;

        while($row = $res_bib->fetchArray(SQLITE3_NUM)) {
            // Books are numbered to leave room for the Apocyrpha in the OT.
            // So, we simply count the books, and don't directly insert source numbers
            if($row[0] != $last_book) {
                $cur_book ++;
                $last_book = $row[0];
            }

            // $text = iconv("UTF-8","UTF-8//IGNORE", $row[3]);
            $this->_addVerse($row[0], $row[1], $row[2], $row[3]);
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

    protected function _formatStrongs_UNUSED($text) {
        $parentheses = $this->strongs_parentheses;
        $subpattern  = ($parentheses == 'trim') ? '/[GHgh][0-9]+/' : '/\(?[GHgh][0-9]+\)?/';

        $default_pattern = '/\{[^\}]+\}/';

        $text = preg_replace_callback('/<W([HG][0-9]+)>/', function($matches) use ($subpattern, $parentheses, $text) {
            return '{' . $matches[1] . '}';
        }, $text);

        return $text;
    }

    public function checkUploadedFile(UploadedFile $File) {
        // $path = $file_tmp_name ?: $file_name;
        $path = $File->getPathname();

        try {
            $info = $this->_getMeta($SQLITE);
            $map = (array_key_exists('description', $info)) ? $this->attribute_map_alt : $this->attribute_map;

            $res_bib = $SQLITE->query('SELECT * FROM Bible ORDER BY Book ASC, Chapter ASC, Verse ASC LIMIT 10');
            $verse_found = FALSE;
            $book = 0;
            $last_book_name = NULL;
            // $desc = iconv("UTF-8","UTF-8//IGNORE", $info['Comments']);

            $this->mapMetaToAttributes($info, FALSE, $map);

            while($row = $res_bib->fetchArray(SQLITE3_NUM)) {
                $book       = (int) $row[0];
                $chapter    = (int) $row[1];
                $verse      = (int) $row[2];
                $text       = trim($row[3]);

                if(!$book || !$chapter || !$verse || !$text) {
                    continue;
                }

                $verse_found = TRUE;
                break;
            }
        }
        catch(\Exception $e) {
            return $this->addError('Could not open MyBible file: ' . $e->getMessage());
        }

        return TRUE;
    }

    private function _getMeta(SQLite3 $SQLITE) {
        $res_desc = $SQLITE->query('SELECT * FROM info');
        $info = [];

        while($row = $res_desc->fetchArray(SQLITE3_ASSOC)) {
            $info[$row['name']] = $row['value'];
        }

        return $info;
    }
}
