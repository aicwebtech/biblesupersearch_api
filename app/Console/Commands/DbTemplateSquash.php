<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DbTemplateSquash extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migration:template
        {--fresh : Drop all DB tables before running migrations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'On template DB, runs migrations then squashes them.';
        

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
        $default_db  = config('database.default');
        $template_db = config('database.template');

        // var_dump($default_db); 
        // var_dump($template_db); 
        // die();

        if($default_db == $template_db) {
            $this->components->info("Default db and template db are the same ({$default_db}), exiting");
            return;
        }

        config(['database.default' => $template_db]);

        if($this->option('fresh')) {            
            // Drops all tables from DB, then runs migration
            $this->call('migrate:fresh', [
                '--database' => $template_db,
            ]);
        } else {            
            $this->call('migrate', [
                '--database' => $template_db,
            ]);
        }

        $this->call('migration:squash', [
            '--database' => $template_db,
            '--path'     => database_path('schema/' . $default_db . '-schema.sql')
        ]);        

        config(['database.default' => $default_db]);
    }
}
