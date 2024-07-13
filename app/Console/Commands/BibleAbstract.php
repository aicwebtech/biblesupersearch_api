<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

abstract class BibleAbstract extends Command 
{
    protected $signature   = '';
    protected $description = '';
    protected $append_signature = TRUE;

    public function __construct() 
    {
        if($this->append_signature) {
            $this->signature .= '{--module=} {--all} {--list}';
        }

        parent::__construct();
    }

    public function handle() 
    {
        if($this->option('list')) {
            $this->_listBibles();
            return;
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

    protected function _getBible() 
    {
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

    protected function _listBibles() 
    {
        Bible::populateBibleTable();
        $Bibles = Bible::orderBy('rank')->get();
        $module_len = 25;
        $name_len = 0;

        foreach($Bibles as $Bible) {
            $module_len = max($module_len, strlen($Bible->module));
            $name_len = max($name_len, strlen($Bible->name));
        }

        print '' . PHP_EOL;
        print 'List of Bibles (automatically refreshed from module files)' . PHP_EOL;
        print '' . PHP_EOL;
        print "\t" . str_pad('Module', $module_len) . "  Installed  Enabled  " . str_pad('Year', 12) . '  ' . str_pad('Name', $name_len) . PHP_EOL;
        print "\t" . str_repeat('-', $module_len + $name_len + 36) . PHP_EOL;

        foreach($Bibles as $Bible) {
            $ena = ($Bible->enabled)   ? 'Yes' : 'No';
            $ins = ($Bible->installed) ? 'Yes' : 'No';
            //$this->print();

            $text = "\t" . str_pad($Bible->module, $module_len) . "  " . str_pad($ins, 9) .  "  " . str_pad($ena, 7) .  "  " . str_pad($Bible->year, 12);
            $text .= '  ' . str_pad($Bible->name, $name_len) . PHP_EOL;

            print $text;
        }

        print '' . PHP_EOL;
        return;
    }

    protected function _getAllBibles() 
    {
        return Bible::all();
    }

    protected function _handleMultipleBibles($Bibles) 
    {
        $Bar = $this->output->createProgressBar(count($Bibles));
        $Bar->setFormatDefinition('custom', ' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% -- %message%                     ' . PHP_EOL);
        $Bar->setFormat('custom');

        foreach($Bibles as $Bible) {
            $Bar->setMessage($Bible->name);
            $this->_handleSingleBible($Bible);
            $Bar->advance();
        }

        $Bar->setMessage('');
        $Bar->finish();
    }

    protected function _handleSingleBible(Bible $Bible) 
    {
        // Extend and do simething here
    }
}
