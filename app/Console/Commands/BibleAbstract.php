<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

abstract class BibleAbstract extends Command {
    protected $signature   = ''; // This abstract command class requires an argument of 'module' at a minimum
    protected $description = '';
    protected $append_signature = TRUE;

    public function __construct() {
        if($this->append_signature) {
            $this->signature .= '{--module=} {--all}';
        }
        
        parent::__construct();
    }

    public function handle() {
        if($this->option('all')) {
            $Bibles = $this->_getAllBibles();
            
            foreach($Bibles as $Bible) {
                $this->_handleSingleBible($Bible);
            }
        }
        else {
            $Bible = $this->_getBible();
            $this->_handleSingleBible($Bible);
        }
    }
    
    protected function _getBible() {
        $module = $this->option('module');
        
        if(!$module) {
            $Bibles = Bible::all();
            $list = array();
            
            foreach($Bibles as $Bible) {
                $list[] = $Bible->module;
            }
            
            $module = $this->anticipate('Please specify a module', $list);
        }
        
        $Bible  = Bible::findByModule($module);
        
        if(!$Bible) {
            throw new \Exception('Bible module \'' . $module . '\' not found');
        }
        
        return $Bible;
    }
    
    protected function _getAllBibles() {
        return Bible::all();
    }
    
    protected function _handleSingleBible(Bible $Bible) {
        // Extend and do simething here
    }
}
