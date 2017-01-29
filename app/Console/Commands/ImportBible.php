<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

abstract class ImportBible extends Command {
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'bible:import';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Import a Bible from a UTF-8 text file';
    protected $options = ['name', 'shortname', 'lang', 'lang_short'];
    protected $import_dir = ''; // Subdirectory of bibles
    protected $file_extension = ''; // Used for filtering file list
    
    protected $hints = array(
        'lang' => [
            'English', 'Spanish', 'Chinese', 'Arabic', 'Hindi', 'French', 'Portuguese', 'Russian', 'German', 'Bengali', 'Malay', 'Urdo', 'Italian',
            'Greek', 'Hebrew',
            ],
        'lang_short' => ['en', 'es', 'zh', 'ar', 'de', 'el', 'he', 'hi', 'fi','fr','bn', 'it', 'ru', 'ms', 'ur']
    );
    
    protected $ask = array(
        'name' => 'Full name of this Bible',
        //'shortname' => 'What is the short display name of this Bible?',
        'lang' => 'Bible language (Full name: \'Spanish\')',
        'lang_short' => 'Language code (\'es\' for Spanish)?',
    );

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct() {
        // Auto-add the arguments and options
        //$this->signature .= ' {file} {module} {--name=} {--shortname=} {--lang=} {--lang_short=} {--overwrite}'; 
        $this->signature .= ' {--file=} {--module=} {--name=} {--shortname=} {--lang=} {--lang_short=} {--overwrite} {--list}'; 
        //$this->description .= '';
        
        parent::__construct();
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle() {
        //
    }
    
    protected function _handleHelper($Importer) {
        //$file       = $this->argument('file');
        //$module     = $this->argument('module');
        $file       = $this->option('file');
        $module     = $this->option('module');
        $overwrite  = $this->option('overwrite');
        $attributes = array();
        
        if($this->option('list')) {
            return $this->_displayFileList();
        }

        $file = ($file) ? $file : $this->anticipate('Input file (Use --list to see all files)', $this->_getFileList());
        
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
        }
        
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
        
        $Importer->setProperties($file, $module, $overwrite, $attributes);
        
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
    public function getImportDir() {
        return dirname(__FILE__) . '/../../../bibles/' . $this->import_dir;
    }
    
    protected function _displayFileList() {
        $list = $this->_getFileList();
        $tab = '    ';

        print PHP_EOL;
        print 'Bibles ready for Import: ' . PHP_EOL;
        print $tab . '(Note: Bible files must be in <biblesupersearch dir>/bibles/' . $this->import_dir . ')' . PHP_EOL;
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
    
    protected function _getFileList() {
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
                
                $list[] = $item;
            }
        }
        
        return $list;
    }
    
    private function _handleErrors($Importer) {
        if($Importer->hasErrors()) {
            foreach($Importer->getErrors() as $error) {
                echo($error . PHP_EOL);
            }
            
            return TRUE;
        }
        
        return FALSE;
    }
}
