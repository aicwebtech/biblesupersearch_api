<?php

namespace aicwebtech\BibleSuperSearch\Console\Commands;

use Illuminate\Console\Command;
use aicwebtech\BibleSuperSearch\Models\Bible;

class EnableBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:enable';

    /**
     * The console command description.
     */
    protected $description = 'Enables an installed Bible Module';

    protected function _handleSingleBible(Bible $Bible) {
        $Bible->enabled = 1;
        $Bible->save();
    }
}
