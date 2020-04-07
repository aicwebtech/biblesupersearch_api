<?php

namespace App\Importers;
use App\Models\Bible;
use ZipArchive;
use SQLite3;
use \DB; //Todo - something is wrong with namespaces here, shouldn't this be automatically avaliable??
use Illuminate\Http\UploadedFile;

/**
 * Imports Bibles in the MySword .mybible (SQLite) format
 *
 * This format is described in full here: https://mysword.info/modules-format
 * Text here is tagged with tags in the 'General Bible Format (GBF) tags patterned after The Word'.  
 *
 * TODO: This importer needs to be able to correctly handle compressed modules (.mybible.gz and .mybible.zip)
 *
 */

class MySword extends ImporterAbstract {
    // protected $required = ['module', 'lang', 'lang_short']; // Array of required fields

    protected $italics_st   = '<FI>';
    protected $italics_en   = '<Fi>';
    protected $redletter_st = '<FR>';
    protected $redletter_en = '<Fr>';
    protected $paragraph    = '<CM>'; // TODO - properly handle paragraphs!
    protected $paragraph_at_verse_end = TRUE;
    protected $unused_tags  = ['RF', 'RX']; // These tags, along with text enclosed betwween them, will be removed. RF = Translators' notes, RX = Cross references
    protected $path_short   = 'mysword';

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
    protected $source = "This Bible imported from MySword <a href='https://mysword.info/download-mysword/bibles'>https://mysword.info/download-mysword/bibles</a>";

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
            $res_desc = $SQLITE->query('SELECT * FROM Details');
            $info = $res_desc->fetchArray(SQLITE3_ASSOC);
            $desc = $info['Comments'];
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

        $res_bib = $SQLITE->query('SELECT Book, Chapter, Verse, Scripture FROM Bible ORDER BY Book ASC, Chapter ASC, Verse ASC');
        
        $i = 0;

        while($row = $res_bib->fetchArray(SQLITE3_NUM)) {
            // $this->_addVerse($row['Book'], $row['Chapter'], $row['Verse'], $row['Scripture']);
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

    protected function _formatStrongs($text) {
        $parentheses = $this->strongs_parentheses;
        $subpattern  = ($parentheses == 'trim') ? '/[GHgh][0-9]+/' : '/\(?[GHgh][0-9]+\)?/';

        $default_pattern = '/\{[^\}]+\}/';

        $text = preg_replace_callback('/<W([HG][0-9]+)>/', function($matches) use ($subpattern, $parentheses, $text) {
            return '{' . $matches[1] . '}';

            // removing of parenthenses - not sure if needed here
            // $st_numbers = [];

            // preg_match_all($subpattern, $matches[0], $submatches);

            // foreach($submatches as $smatch) {
            //     if($parentheses == 'discard' && $smatch[0]{0} == '(') {
            //         continue;
            //     }

            //     if(isset($smatch[0])) {
            //         $st_numbers[] = '{' . $smatch[0] . '}';
            //     }
            // }

            // return (count($st_numbers)) ? implode(' ', $st_numbers) : $matches[0];
        }, $text);

        return $text;
    }

    protected function _postFormatText($text) {
        $text = parent::_postFormatText($text);
        $text = strip_tags($text);
        return $text;
    }

    public function checkUploadedFile(UploadedFile $File) {
        // $path = $file_tmp_name ?: $file_name;
        $path = $File->getPathname();

        try {
            $SQLITE = new SQLite3($path);
            $res_desc = $SQLITE->query('SELECT * FROM Details');
            $info = $res_desc->fetchArray(SQLITE3_ASSOC);

            // var_dump($info);

            $map = (array_key_exists('description', $info)) ? $this->attribute_map_alt : $this->attribute_map;

            $res_bib = $SQLITE->query('SELECT * FROM Bible ORDER BY Book ASC, Chapter ASC, Verse ASC LIMIT 10');
            $verse_found = FALSE;
            $book = 0;
            $last_book_name = NULL;
            // $desc = iconv("UTF-8","UTF-8//IGNORE", $info['Comments']);

            $this->mapMetaToAttributes($info, FALSE, $map);

            // $this->bible_attributes = [
            //     'name'          => $info['Description'],
            //     'shortname'     => $info['Abbreviation'],
            //     'module'        => static::generateUniqueModuleName($info['Abbreviation']),
            //     'description'   => array_key_exists('Comments', $info) ?  $info['Comments'] . '<br /><br />' . $this->source : $this->source,
            //     'year'          => $info['PublishDate'],
            //     'lang_short'    => array_key_exists('Language', $info) ? static::getLanguageCode($info['Language']) : NULL,
            // ];

            while($row = $res_bib->fetchArray(SQLITE3_NUM)) {
                // $book    = intval($row['Book']);
                // $chapter = intval($row['Chapter']);
                // $verse   = intval($row['Verse']);
                // $text    = trim($row['Scripture']);

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
            return $this->addError('Could not open MySword file: ' . $e->getMessage());
        }

        return TRUE;
    }
}
