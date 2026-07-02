<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use App\Models\Bible;
use App\Models\Language;
use App\Models\LanguageAttr;
use App\Models\Feature;
use App\ConfigManager;
use App\User;

class AppInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:install  {--bypass-prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the application to database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if(config('app.installed')) {
            $this->error('Application is already installed.');
            return;
        }

        $bypass = $this->option('bypass-prompts');

        if(!$bypass) {
            if (!$this->confirm('This will install the application to the database. Do you wish to continue?')) {
                $this->info('Installation cancelled.');
                return;
            }
        }

        // :todo make a shared installer with InstallManager
        // This is a basic installer
        $this->call('migrate', ['--force' => true]);

        // Set 'installed' config
        ConfigManager::setConfigs(['app.installed' => TRUE]);

        // Create default admin user when bypassing prompts (e.g. CI)
        if ($bypass && User::count() === 0) {
            $password = Str::password(16);

            $User = new User;
            $User->name     = 'Admin';
            $User->username = 'admin';
            $User->email    = 'admin@example.com';
            $User->password = bcrypt($password);
            $User->save();
            $User->access_level = 100;
            $User->save();

            $this->warn("Admin user created with password: {$password}");
            $this->warn('Store this now and change it after first login.');
        }

        // Populate the Bible table
        Bible::populateBibleTable();

        // Populate the Features table
        Feature::syncFeatures();

        // Install default Bible (usally KJV)
        $Bible = Bible::findByModule(config('bss.defaults.bible'));
        if (!$Bible) {
            $this->error("Default Bible module '" . config('bss.defaults.bible') . "' not found.");
            return;
        }
        $Bible->install(FALSE, TRUE);
        // Set up book lists for EN language
        \App\Models\Books\BookAbstract::createTableAndMigrateFromCsv('en');
        $EN = Language::findByCode('en');
        $EN->setAttr('book_list', 1);   
        // English common words. This is used for the common words feature, which helps to improve search results by identifying common words in each language that should be ignored or treated differently in searches.
        // :todo this should be part of the language CSV. 
        $EN->common_words = "a\nan\nand\nare\nas\nat\nbe\nby\nfor\nhe\nhis\nin\nis\nit\nof\non\nor\nthat\nthe\nthey\nto\nwas\nwith\nyou";
        $EN->save();

        if(!config('app.installed')) {
            $this->error('Installation failed. Please check the logs for details.');
        } else {
            $this->info('Application installed successfully!');
        }
    }
}
