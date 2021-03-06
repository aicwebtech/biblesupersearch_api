<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

class InstallBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:install {--module=} {--all} {--enable} {--list}';
    protected $append_signature = FALSE;

    /**
     * The console command description.
     */
    protected $description = 'Install a Bible Module';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        if($this->option('list')) {
            return $this->_listBibles();
        }
        
        if($this->option('all')) {
            Bible::populateBibleTable();
            $Bibles = Bible::all();
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
        
        if($this->option('enable')) {
            $Bible->enabled = 1;
            $Bible->save();
        }
    }
}
