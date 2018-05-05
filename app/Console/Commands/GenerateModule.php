<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

class GenerateModule extends BibleAbstract {
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:export {--module=} {--all} {--list} --overwrite';
    protected $append_signature = FALSE;

    /**
     * The console command description.
     */
    protected $description = 'Export a Bible Module into a Standard Module File';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleSingleBible(Bible $Bible) {
        $Bible->export(TRUE);
    }
}
