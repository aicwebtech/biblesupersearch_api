<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ListBibles extends BibleAbstract
{
    protected $append_signature = FALSE;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists all Bibles currently available';

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
        return $this->_listBibles();
    }
}
