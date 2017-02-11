<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

class UninstallBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:uninstall {module} {--hard}';

    /**
     * The console command description.
     */
    protected $description = 'Uninstalls a Bible Module';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    protected function _handleSingleBible(Bible $Bible) {
        $Bible = $this->_getBible();
        $Bible->uninstall();
        
        if($this->option('hard')) {
            $Bible->delete();
        }
    }
}
