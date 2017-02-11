<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

class DisableBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:disable';

    /**
     * The console command description.
     */
    protected $description = 'Disables a Bible Module';

    protected function _handleSingleBible(Bible $Bible) {
        $Bible->enabled = 0;
        $Bible->save();
    }
}
