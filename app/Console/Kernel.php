<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{

    // Upgrade note: Commands can be auto loaded by adding this line to 'commands' method
    // $this->load(__DIR__.'/Commands');

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\Inspire::class,
        Commands\AppVersion::class,
        Commands\BibleRender::class,
        Commands\RenderExtras::class,
        Commands\ImportBibleUnbound::class,
        Commands\ImportBibleAnalyzer::class,
        // Commands\ImportBibleEvening::class, // Obsolete
        Commands\ImportBibleMySword::class,
        Commands\ImportBibleUsfm::class,
        //Commands\ImportBibleCustom::class,  // DEV TOOL: Comment out when not using
        // Commands\BibleOfficial::class, // DEV TOOL: Mark Bible official, Comment out when not using
        Commands\ListBibles::class,
        Commands\EnableBible::class,
        Commands\DisableBible::class,
        Commands\InstallBible::class,
        Commands\UninstallBible::class,
        Commands\CompareBibles::class,
        Commands\CompareBiblesAll::class,
        Commands\GenerateModule::class,
        Commands\BibleRefresh::class,
        Commands\TempMigrateStrongs::class,
        Commands\TestEmail::class,
        Commands\UserCreate::class,
        Commands\UserPassword::class,
        Commands\MigrateModuleFiles::class,
        Commands\MigrationSquash::class,
        Commands\DbTemplateSquash::class,
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

    public function __construct(\Illuminate\Contracts\Foundation\Application $app, \Illuminate\Contracts\Events\Dispatcher $events) 
    {
        $this->bootstrappers[] = \App\Http\Bootstrap\LoadSoftConfiguration::class;

        parent::__construct($app, $events);
    }
}
