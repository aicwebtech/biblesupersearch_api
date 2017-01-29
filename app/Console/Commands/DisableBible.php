<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DisableBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:disable {module}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Disables a Bible Module';

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
        $Bible->enabled = 0;
        $Bible->save();
    }
}
