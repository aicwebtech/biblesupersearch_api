<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct() {
        // Auto-add the arguments and options
        $this->signature .= ' {file} {module} {--name=} {--shortname=} {--lang=} {--lang_short=} {--overwrite}'; 
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
        $file       = $this->argument('file');
        $module     = $this->argument('module');
        $overwrite  = $this->option('overwrite');
        $attributes = array();
        
        foreach($this->options as $option) {
            $attributes[$option] = $this->option($option);
        }
        
        $Importer->setProperties($file, $module, $overwrite, $attributes);
        
        // Settings errors check
        if(!$this->_handleErrors($Importer)) {
            $Importer->import();
            $this->_handleErrors($Importer); // Import errors check
        }
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
