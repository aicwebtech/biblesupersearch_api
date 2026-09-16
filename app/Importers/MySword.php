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

class MySword extends ImporterAbstract 
{
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
            'encryption'    => 'Encryption',
    ];    

    protected $attribute_map_alt = [
            'name'          => 'description',
            'shortname'     => 'abbreviation',
            'module'        => 'abbreviation',
            'description'   => 'Comments',
            'year'          => 'publishdate',
            'lang_short'    => 'language',
            'encryption'    => 'encryption',
    ];

    // Where did you get this Bible?
    protected $source = "This Bible imported from MySword <a href='https://mysword.info/download-mysword/bibles'>https://mysword.info/download-mysword/bibles</a>";

    protected function _importHelper(Bible &$Bible): bool 
    {
        if(config('app.env') != 'testing') {
            ini_set("memory_limit", "50M");
        }

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

        $SQLITE = $this->_getSQLite($filepath, $file);

        if(!$SQLITE) {
            return $this->addError('Could not open MySword file');
        }

        if($this->insert_into_bible_table) {
            $res_desc = $SQLITE->query('SELECT * FROM Details');
            $info = $res_desc->fetchArray(SQLITE3_ASSOC);
            $desc = $info['Comments'];
            $attr = $this->bible_attributes;
            $attr['description'] = $desc . '<br /><br />' . $this->source;
            
            if(array_key_exists('year', $attr)) {
                $attr['year'] = date('Y', strtotime($attr['year']));
            }

            $Bible->fill($attr);
            $Bible->save();
        }

        $Bible->install(TRUE);
        $Verses = $Bible->verses();
        $table  = $Verses->getTable();

        if(config('app.env') != 'testing' && \App::runningInConsole()) {
            echo('Installing: ' . $module . PHP_EOL);
        }

        $res_bib = $SQLITE->query('SELECT Book, Chapter, Verse, Scripture FROM Bible ORDER BY Book ASC, Chapter ASC, Verse ASC');
        
        $i = 0;

        while($row = $res_bib->fetchArray(SQLITE3_NUM)) {
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

    protected function _formatText($text) 
    {
        $text    = $this->_preFormatText($text);
        $text    = $this->_formatStrongs($text);
        $text    = $this->_formatItalics($text);
        $text    = $this->_formatRedLetter($text);
        $text    = $this->_formatParagraph($text);
        $text    = $this->_removeUnusedTags($text);
        $text    = $this->_postFormatText($text);
        return $text;
    }

    protected function _formatStrongs($text) 
    {
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

    public function checkUploadedFile(UploadedFile $File): bool  
    {
        // $path = $file_tmp_name ?: $file_name;
        $path = $File->getPathname();

        try {
            // $SQLITE = new SQLite3($path);
            $SQLITE = $this->_getSQLite($path, $File->getClientOriginalName());

            if(!$SQLITE) {
                return $this->addError('Could not open MySword file');
            }

            $res_desc = $SQLITE->query('SELECT * FROM Details');
            $info = $res_desc->fetchArray(SQLITE3_ASSOC);

            $map = (array_key_exists('description', $info)) ? $this->attribute_map_alt : $this->attribute_map;

            if(array_key_exists($map['encryption'], $info) && $info[ $map['encryption'] ]) {
                return $this->addError('This Bible is encrypted, and cannot be imported.');
            }

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
            return $this->addError('Could not open MySword file: ' . $e->getMessage());
        }

        return TRUE;
    }

    public function mapMetaToAttributes($meta, $preserve_attributes = FALSE, $map = NULL) 
    {
        parent::mapMetaToAttributes($meta, $preserve_attributes, $map);
        $this->bible_attributes['year'] = $this->bible_attributes['year'] ? date('Y', strtotime($this->bible_attributes['year'])) : null;
    }

    /**
     * Open the SQLite database inside an uploaded MySword file.
     *
     * $orig_filename may be the raw client filename (preflight) or the stored
     * name (commit), so it is sanitized to a bare basename here and every
     * derived path is required to stay inside the importer directory.
     *
     * @param  string  $path           Path to the archive on disk
     * @param  string  $orig_filename  Untrusted filename
     * @return \SQLite3|bool
     */
    private function _getSQLite($path, $orig_filename) 
    {
        $orig_filename = static::sanitizeFileName((string) $orig_filename);

        if($orig_filename === '' || $orig_filename !== basename($orig_filename)) {
            return $this->addError('Invalid import filename');
        }

        $path_lc = strtolower($orig_filename);
        $dir = $this->getImportDir();

        if(!is_file($path)) {
            return $this->addError('File does not exist');
        }

        if(str_ends_with($path_lc, '.mybible.zip')) {
            $uz_file = substr($orig_filename, 0, -4);
            $uz_path = $this->_containedImportPath($uz_file);

            if(!$uz_path) {
                return $this->addError('Invalid import filename');
            }

            $Zip = new \ZipArchive;

            if($Zip->open($path) === TRUE) {
                try {
                    if(!$this->_zipEntryWithinLimit($Zip, $uz_file)) {
                        return $this->addError('Refusing to extract .zip file: uncompressed size exceeds the allowed limit');
                    }

                    if(!$Zip->extractTo($dir, $uz_file)) {
                        return $this->addError('Could not extract from .zip file');
                    }
                }
                finally {
                    $Zip->close();
                }

                return new SQLite3($uz_path);
            }
            else {
                return $this->addError('Could not open .zip file');
            }
        }        
        else if(str_ends_with($path_lc, '.mybible.gz')) {
            $uz_file = substr($orig_filename, 0, -3);
            $uz_path = $this->_containedImportPath($uz_file);

            if(!$uz_path) {
                return $this->addError('Invalid import filename');
            }
            
            // 'Extracting' from a .gz file
            // Raising this value may increase performance
            $buffer_size = 4096; // read 4kb at a time
            $limit = static::maxDecompressedBytes();
            $written = 0;

            // Open our files (in binary mode)
            $in_file  = gzopen($path, 'rb');
            $out_file = fopen($uz_path, 'wb');

            if(!$in_file || !$out_file) {
                if($in_file) { gzclose($in_file); }
                if($out_file) { fclose($out_file); }
                return $this->addError('Could not open .gz file');
            }

            $failed = FALSE;

            try {
                // Keep repeating until the end of the input file
                while(!gzeof($in_file)) {
                    // Both fwrite and gzread and binary-safe
                    $chunk = gzread($in_file, $buffer_size);

                    if($chunk === FALSE) {
                        $failed = 'Could not read .gz file';
                        break;
                    }

                    $written += strlen($chunk);

                    // A small archive can expand without bound; stop rather than
                    // filling the disk.
                    if($written > $limit) {
                        $failed = 'Refusing to extract .gz file: uncompressed size exceeds the allowed limit';
                        break;
                    }

                    if(fwrite($out_file, $chunk) === FALSE) {
                        $failed = 'Could not write extracted file';
                        break;
                    }
                }
            }
            finally {
                fclose($out_file);
                gzclose($in_file);
            }

            if($failed) {
                @unlink($uz_path); // do not leave a partial database behind
                return $this->addError($failed);
            }

            return new SQLite3($uz_path);
        }        
        else if(str_ends_with($path_lc, '.mybible')) {
            return new SQLite3($path);
        }

        return FALSE;
    }

    /**
     * Maximum permitted size of a decompressed MySword database, in bytes.
     *
     * @return int
     */
    public static function maxDecompressedBytes() 
    {
        return (int) config('bible.max_decompressed_bytes', 1073741824); // 1 GiB
    }

    /**
     * Build a path for an extracted file and require it to stay inside the
     * importer directory.
     *
     * Also refuses a destination that already exists as a symlink. Both writers
     * fed by this path follow one and write through to its target: fopen($path,
     * 'wb') on the .gz branch, and ZipArchive::extractTo() on the .zip branch --
     * verified, extractTo() returns TRUE while overwriting the link target. The
     * importer only ever writes real files here, so a link is never something it
     * left behind; refuse rather than unlink and write through it.
     *
     * @param  string  $basename
     * @return string|null
     */
    protected function _containedImportPath($basename) 
    {
        $basename = static::sanitizeFileName((string) $basename);

        if($basename === '' || $basename !== basename($basename)) {
            return NULL;
        }

        $dir = realpath($this->getImportDir());

        if($dir === FALSE) {
            return NULL;
        }

        $path = $dir . DIRECTORY_SEPARATOR . $basename;

        // The file does not exist yet, so canonicalize the parent instead.
        if(dirname($path) !== $dir) {
            return NULL;
        }

        if(is_link($path)) {
            return NULL;
        }

        return $path;
    }

    /**
     * Is the named zip entry within the decompressed-size limit?
     *
     * @param  \ZipArchive  $Zip
     * @param  string       $entry
     * @return bool
     */
    protected function _zipEntryWithinLimit(\ZipArchive $Zip, $entry) 
    {
        $stat = $Zip->statName($entry);

        if($stat === FALSE) {
            return TRUE; // entry absent; extractTo will fail on its own
        }

        return ($stat['size'] ?? 0) <= static::maxDecompressedBytes();
    }
}
