<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;
use App\Models\Language;
use App\Models\Books\En as Book;

abstract class ImportBible extends Command 
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'bible:import';

    /**
     * The console command description.
     * @var string
     */
    protected $name = 'UTF-8';
    protected $description = 'Import a Bible from a UTF-8 text file';
    protected $exe_descr = '';
    protected $options = ['name', 'shortname', 'lang', 'lang_short'];
    protected $import_dir = ''; // Subdirectory of bibles
    protected $file_extension = ''; // Used for filtering file list
    protected $require_file = TRUE;
    protected $ProgressBar = null;

    protected $hints = array(
        'lang' => [
            //'English', 'Spanish', 'Chinese', 'Arabic', 'German', 'Greek', 'Hebrew', 'Hindi', 'French', 'Portuguese', 'Russian', 'Bengali', 'Malay', 'Urdo', 'Italian',
        ],
        'lang_short' => [], // ['en', 'es', 'zh', 'ar', 'de', 'el', 'he', 'hi', 'fi','fr','bn', 'it', 'ru', 'ms', 'ur']
    );

    protected $ask = array(
        'name' => 'Full name of this Bible',
        //'shortname' => 'What is the short display name of this Bible?',
        'lang' => 'Bible language (Full name: \'Spanish\')',
        // 'lang_short' => 'Language code (\'es\' for Spanish)?',
    );

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct() 
    {
        // Auto-add the arguments and options
        //$this->signature .= ' {file} {module} {--name=} {--shortname=} {--lang=} {--lang_short=} {--overwrite}';
//        $this->signature .= ' {--file=} {--module=} {--name=} {--shortname=} {--lang=} {--lang_short=} {--overwrite} {--list}';
        $this->signature .= ' {--file= : File to import} '
                . '{--module= : Module name - internal, unique identifer for this Bible, ie NIV2011} '
                . '{--name= : Full text name of this Bible, ie "New International Version"} '
                . '{--shortname= : Short display name of this Bible, ie "NIV"} '
                . '{--lang= : This Bible\'s language, full name} '
                . '{--lang_short= : This Bible\'s language as a 2 character ISO 639-1 code} '
                . '{--overwrite : whether to overwrite the existing Bible of the module name, if it exists} '
                . '{--list : lists all avaliable files for this importer}'
                . '{--debug : Attempt to debug importer using preseleted Bible and module name  }';
        //$this->description .= '';

        if($this->require_file && $this->import_dir) {
            $this->description .= PHP_EOL . '                              Files to be imported need to be placed in ' . $this->getImportDirReadable();
        }

        $this->exe_descr = $this->description;
        $this->description .= PHP_EOL . PHP_EOL . 'Hint: Run without argments to be prompted for them.';

        parent::__construct();

        // For some weird reason the MIGRATION that creates the langage table BREAKS here!
        // Not sure why that's even touching this completely unrelated class!
        if (\Schema::hasTable('languages')) {
            $Languages = Language::orderBy('name', 'asc')->get();

            foreach($Languages as $Lang) {
                $this->hints['lang'][]       = $Lang->name;
                $this->hints['lang_short'][] = $Lang->code;
            }
        }
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle() 
    {
        //
    }

    protected function _handleHelper($Importer) 
    {
        //$file       = $this->argument('file');
        //$module     = $this->argument('module');
        $file       = $this->option('file');
        $module     = $this->option('module');
        $overwrite  = $this->option('overwrite');
        $attributes = array();
        $autopopulate   = FALSE;
        $debug = false;

        $Importer->setBeforeImportBible([$this, 'importStart']);
        $Importer->setOnAddVerse([$this, 'importVerseAdd']);
        $Importer->setAfterImportBible([$this, 'importEnd']);

        if($this->option('list')) {
            return $this->_displayFileList();
        }

        if($this->option('debug')) {
            $debug = true;
            $Importer->debug = true;
            $this->require_file = false;
            $module = 'auto_' . time();
        }

        if($this->require_file && !$file) {
            echo(PHP_EOL . $this->exe_descr . PHP_EOL);

            $file = $this->anticipate('Input file (Use --list to see all available files)', $this->_getFileList());
        }

        if($file == '--list') {
            return $this->_displayFileList();
        }

        while(!$module) {
            $mod = $this->ask('Module Name (lower case characters, digits and underscores only)');
            $filtered = preg_replace('/[a-z_0-9]*/', '', $mod);

            if($mod && empty($filtered)) {
                $module = $mod;
            }
            else {
                print PHP_EOL . 'Invalid module name.  Module name may only contain lower case characters, digits and underscores' . PHP_EOL;
            }
        }

        $Bible = Bible::findByModule($module);

        if($Bible && !$overwrite) {
            $overwrite = $this->confirm('Module \'' . $module . '\' already exists.  Overwrite? [y|N]');

            if(!$overwrite) {
                return;
            }

            $autopopulate = $this->confirm('Use existing Bible attributes? [y|N]');
        }

        if(!$autopopulate && !$debug) {
            foreach($this->options as $option) {
                $attributes[$option] = $this->option($option);
            }
            
            foreach($this->ask as $field => $question) {
                if(empty($attributes[$field])) {
                    if(isset($this->hints[$field])) {
                        $attributes[$field] = $this->anticipate($question, $this->hints[$field]);
                    }
                    else {
                        $attributes[$field] = $this->ask($question);
                    }
                }
            }

            $lang_pos = array_search($attributes['lang'], $this->hints['lang']);

            if($lang_pos !== FALSE) {
                $attributes['lang_short'] = $this->hints['lang_short'][$lang_pos];
            }
            else {
                $lang_short = $this->option('lang_short');

                while(!$lang_short) {
                    $lang_short = $this->ask('What is the 2 character ISO 639-1 code for the language "' . $attributes['lang'] . '"? '
                            . ' (See http://www.loc.gov/standards/iso639-2/php/code_list.php)');

                    if(strlen($lang_short) != 2) {
                        $lang_short = NULL;
                        continue;
                    }

                    $lang_short = strtolower($lang_short);
                    $Existing = Language::where('code', $lang_short)->first();

                    if($Existing) {
                        echo('ISO 639-1 code "' . $lang_short . '" is already attributed to the language "' . $Existing->name . '"' . PHP_EOL);
                        $cor = $this->confirm('Is this correct?');

                        if($cor) {
                            $lang_short = NULL;
                            continue;
                        }
                        else {
                            throw new \Exception('This importer cannot currently fix pre-existing issues in the language table');
                        }
                    }
                }

                $Lang = new Language;
                $Lang->name = $attributes['lang'];
                $Lang->code = $lang_short;
                $Lang->save();

                $attributes['lang_short'] = $lang_short;

                // throw new \Exception('Language ' . $attributes['lang'] . ' not found');
            }
        }

        $Importer->setProperties($file, $module, $overwrite, $attributes, $autopopulate);

        // Settings errors check
        if(!$this->_handleErrors($Importer)) {
            $Importer->import();
            $this->_handleErrors($Importer); // Import errors check
        }
    }

    /**
     * Returns the fully qualified import directory
     * @return type
     */
    public function getImportDir() 
    {
        return dirname(__FILE__) . '/../../../bibles/' . $this->import_dir;
    }

    public function getImportDirReadable() 
    {
        return getcwd() . '/bibles/' . $this->import_dir;
    }

    public function importStart(Bible $Bible)
    {
        $this->ProgressBar = $this->output->createProgressBar(31102);
        $this->ProgressBar->setFormatDefinition('custom', ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% -- %message%                     ' . PHP_EOL);
        $this->ProgressBar->setFormat('custom');
        $this->_book = 0;
        $this->_chapter = 0;
    }    

    public function importEnd(Bible $Bible)
    {
        $this->ProgressBar->setMessage('');
        $this->ProgressBar->finish();
    }    

    public function importVerseAdd($book, $chapter, $verse, $text)
    {
        if($this->_book != $book) {
            $this->_book = $book;
            $this->_Book = Book::find($book);
        }
            // $Book = Book::find($book);
            $this->ProgressBar->setMessage($this->_Book->name .  ' ' . $chapter . ':' . $verse );
            $this->ProgressBar->advance();
    }

    protected function _displayFileList() 
    {
        $list = $this->_getFileList();
        $tab = '    ';

        print PHP_EOL;
        print 'Bibles ready for Import: ' . PHP_EOL;
        print $tab . '(Note: Bible files for this specific importer must be placed in ' . $this->getImportDirReadable() . ')' . PHP_EOL;
        print PHP_EOL;

        foreach($list as $item) {
            print $tab . $tab . $item . PHP_EOL;
        }

        if(empty($list)) {
            print $tab . 'NO BIBLES FOUND!' . PHP_EOL;
        }
        else {
            print PHP_EOL;
            print $tab . count($list) . ' Bibles found' . PHP_EOL;
        }

        print PHP_EOL;
        return;
    }

    protected function _getFileList() 
    {
        $dir = $this->getImportDir();
        $list = array();

        if(is_dir($dir)) {
            $list_raw = scandir($dir);

            foreach($list_raw as $item) {
                if($item == '.' || $item == '..' || $item == 'readme.txt') {
                    continue;
                }

                if($this->file_extension && !preg_match('/\.(' . $this->file_extension . ')$/i', $item)) {
                    continue;
                }

                if($this->_filterFileItem($item)) {
                    $list[] = $item;
                }
            }
        }

        return $list;
    }

    // Hook / filter for file list items to verify they are good for this importer
    protected function _filterFileItem($item)
    {
        return true;
    }

    private function _handleErrors($Importer) 
    {
        if($Importer->hasErrors()) {
            foreach($Importer->getErrors() as $error) {
                echo($error . PHP_EOL);
            }

            return TRUE;
        }

        return FALSE;
    }
}
