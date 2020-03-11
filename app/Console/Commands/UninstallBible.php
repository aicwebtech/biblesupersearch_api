<?php

namespace aicwebtech\BibleSuperSearch\Console\Commands;

use Illuminate\Console\Command;
use aicwebtech\BibleSuperSearch\Models\Bible;

class UninstallBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:uninstall {--module=} {--list} {--all} {--hard}';
    protected $append_signature = FALSE;

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
        $Bible->uninstall();
        
        if($this->option('hard')) {
            $Bible->delete();
        }
    }
}
