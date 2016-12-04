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

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct() {
        // Auto-add the arguments and options
        $this->signature .= ' {file} {module} {name?} {shortname?}'; 
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
        $attributes = array();
        $attributes['name']      = $this->argument('name');
        $attributes['shortname'] = $this->argument('shortname');
        
        $Importer->setProperties($file, $module, $attributes);
        $Importer->import();
    }
}
