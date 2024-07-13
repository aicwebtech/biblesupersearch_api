<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

class InstallBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:install {--module=} {--all} {--enable} {--list} {--testing}';
    protected $append_signature = false;
    protected $force_enable = false;

    /**
     * The console command description.
     */
    protected $description = 'Install a Bible Module';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() 
    {
        if($this->option('list')) {
            return $this->_listBibles();
        }
        
        if($this->option('all')) {
            Bible::populateBibleTable();
            $Bibles = Bible::all();
            $this->_handleMultipleBibles($Bibles);
            return;
        }        

        // Installs and enables ALL Bibles needed for PHPUnit testing
        if($this->option('testing')) {
            Bible::populateBibleTable();
            $this->force_enable = true;
            $list = config('bible.testing');
            $Bibles = Bible::whereIn('module', $list)->get();
            $this->_handleMultipleBibles($Bibles);
            return;
        }
        
        $module = $this->option('module');
        $Bible  = Bible::createFromModuleFile($module);
        
        if(!$Bible) {
            $Bible = $this->_getBible();
        }
        
        $this->_handleSingleBible($Bible);
    }
    
    protected function _handleSingleBible(Bible $Bible) {
        $Bible->install();
        
        if($this->force_enable || $this->option('enable')) {
            $Bible->enabled = 1;
            $Bible->save();
        }
    }
}
