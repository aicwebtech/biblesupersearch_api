<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;
use \DB;

class CompareBiblesAll extends CompareBibles
{
    protected $append_signature = FALSE;
    protected $quiet = true;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:compare-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compares all Bible to the default Bible';

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

        $default = config('bss.defaults.bible');

        $BibleDefault = Bible::findByModule($default);
        // $Bible1 = Bible::findByModule($module1);
        // $Bible2 = Bible::findByModule($module2);

        $Bibles = Bible::where('installed', 1) -> get();

        foreach($Bibles as $Bible) {
            if($Bible->module == $default) {
                continue;
            }

            $this->handleHelper($BibleDefault, $Bible);
        }
    }

}
