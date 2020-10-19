<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\RenderManager;
use App\ProcessManager;

class RenderExtras extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:render:extras {format : name of format} {--overwrite : whether to overwrite existing file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renders the selected Bibles into the specified format';

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
    public function handle() {
        // if (posix_getpid() != posix_getsid(getmypid())) {
        //     posix_setsid();
        // }

        $format     = [ $this->argument('format') ];
        $overwrite  = $this->option('overwrite');

        $Manager = new RenderManager([], $format, FALSE);
        $Manager->renderExtras($overwrite, TRUE, TRUE);

        if($Manager->hasErrors()) {
            echo('Errors have occurred:' . PHP_EOL);

            foreach($Manager->getErrors() as $error) {
                echo('    ' . $error . PHP_EOL);
            }
        }
        
    }
}
