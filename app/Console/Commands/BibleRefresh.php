<?php

namespace aicwebtech\BibleSuperSearch\Console\Commands;

use Illuminate\Console\Command;
use aicwebtech\BibleSuperSearch\Models\Bible;

class BibleRefresh extends Command {
    protected $signature = 'bible:refresh';
    protected $description = 'Scans the module directory and adds Bibles not already present in Bibles list';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        Bible::populateBibleTable();
    }
}
