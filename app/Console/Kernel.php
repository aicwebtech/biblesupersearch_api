<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\Inspire::class,
        Commands\ImportBibleUnbound::class,
        Commands\ImportBibleAnalyzer::class,
        Commands\ImportBibleEvening::class,
        // Commands\ImportBibleCustom::class,  // Comment out when not using
        Commands\EnableBible::class,
        Commands\DisableBible::class,
        Commands\InstallBible::class,
        Commands\UninstallBible::class,
        Commands\GenerateModule::class,
        Commands\BibleRefresh::class,
        Commands\TempMigrateStrongs::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('inspire')
                 ->hourly();

        $schedule->call(function() {
            $CM = new \App\CacheManager();
            $CM->cleanUpCache();
        })->weekly();
    }

    public function __construct(\Illuminate\Contracts\Foundation\Application $app, \Illuminate\Contracts\Events\Dispatcher $events) {
        $this->bootstrappers[] = \App\Http\Bootstrap\LoadSoftConfiguration::class;

        parent::__construct($app, $events);
    }
}
