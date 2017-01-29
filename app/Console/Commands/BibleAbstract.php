<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

abstract class BibleAbstract extends Command {
    protected $signature   = ''; // This abstract command class requires an argument of 'module' at a minimum
    protected $description = '';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        //
    }
    
    protected function _getBible() {
        $module = $this->argument('module');
        $Bible  = Bible::findByModule($module);
        
        if(!$Bible) {
            throw new Exception('Bible module \'' . $module . '\' not found');
        }
        
        return $Bible;
    }
}
