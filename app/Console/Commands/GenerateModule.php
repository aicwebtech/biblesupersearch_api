<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateModule extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:export {module} --overwrite';

    /**
     * The console command description.
     *
     * @var string
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

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $Bible = $this->_getBible();
        $Bible->export();
    }
}
