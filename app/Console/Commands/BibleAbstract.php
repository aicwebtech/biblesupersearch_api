<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

abstract class BibleAbstract extends Command {
    protected $signature   = '';
    protected $description = '';
    protected $append_signature = TRUE;

    public function __construct() {
        if($this->append_signature) {
            $this->signature .= '{--module=} {--all} {--list}';
        }
        
        parent::__construct();
    }

    public function handle() {
        if($this->option('list')) {
            return $this->_listBibles();
        }
        
        if($this->option('all')) {
            $Bibles = $this->_getAllBibles();
            $this->_handleMultipleBibles($Bibles);
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
    
    protected function _listBibles() {                    
        print '' . PHP_EOL;
        print 'List of Bibles (automatically refreshed from module files)' . PHP_EOL;
        print '' . PHP_EOL;
        print "\t" . str_pad('Module', 10) . "\tInstalled  Enabled  " . 'Name' . PHP_EOL;
        print "\t" . str_repeat('-', 80) . PHP_EOL;
        
        Bible::populateBibleTable();
        
        foreach(Bible::all() as $Bible) {
            $ena = ($Bible->enabled)   ? 'Yes' : 'No';
            $ins = ($Bible->installed) ? 'Yes' : 'No';
            //$this->print();
            print "\t" . str_pad($Bible->module, 10) . "\t" . str_pad($ins, 9) .  "  " . str_pad($ena, 7) .  "  " . $Bible->name . PHP_EOL;
        } 
        
        print '' . PHP_EOL;
        return;
    }
    
    protected function _getAllBibles() {
        return Bible::all();
    }
    
    protected function _handleMultipleBibles($Bibles) {
        $Bar = $this->output->createProgressBar(count($Bibles));
        $Bar->setFormatDefinition('custom', ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% -- %message%                     ' . PHP_EOL);
        $Bar->setFormat('custom');

        foreach(Bible::all() as $Bible) {
            $this->_handleSingleBible($Bible);
            $Bar->setMessage($Bible->name);
            $Bar->advance();
        } 

        $Bar->finish();
    }
    
    protected function _handleSingleBible(Bible $Bible) {
        // Extend and do simething here
    }
}
