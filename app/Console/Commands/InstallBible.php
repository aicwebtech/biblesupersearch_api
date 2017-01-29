<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

class InstallBible extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:install {module} --enable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install a Bible Module';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $module = $this->argument('module');
        
        $Bible = $this->_getBible();
    }
}
