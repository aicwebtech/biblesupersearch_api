<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\RenderManager;
use App\ProcessManager;

class BibleRender extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:render {format : name of format} {bible : single module or comma-separated list}' . 
        '{--overwrite : whether to overwrite existing file}' . 
        '{--extras : whether to include extra files, such as Bible book lists}' . 
        '{--ignore-render-errors : whether to ignore render errors for specific Bibles and return the rest}' . 
        '{--debug : run in debug mode (quick exit}';

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
    public function handle() 
    {
        // if (posix_getpid() != posix_getsid(getmypid())) {
        //     posix_setsid();
        // }

        $format     = [ $this->argument('format') ];
        $bible      = $this->argument('bible');
        $overwrite  = $this->option('overwrite');
        $extras     = $this->option('extras');
        $debug      = $this->option('debug');
        $ignore_render_errors = $this->option('ignore-render-errors');
        $bible      = ($bible == 'ALL' || $bible == 'OFFICIAL') ? $bible : explode(',', $bible);

        $start = time();

        $Manager = new RenderManager($bible, $format, FALSE, $this->output);
        $Manager->include_extras = $extras;
        $Manager->debug = $debug;
        $Manager->render($overwrite, TRUE, TRUE);
        $Manager->download(TRUE, TRUE, TRUE, TRUE);

        if($Manager->hasErrors()) {
            echo('Errors have occurred:' . PHP_EOL);

            foreach($Manager->getErrors() as $error) {
                echo('    ' . $error . PHP_EOL);
            }
        }

        echo('Total time: ' . (time() - $start) . ' seconds' . PHP_EOL);
        
    }
}
