<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
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

            $adminUser = $this->promptForAdminUser();
        } else {
            $adminUser = [
                'name'     => 'Admin',
                'username' => 'admin',
                'email'    => 'admin@example.com',
                'password' => 'admin',
            ];
        }

        // :todo make a shared installer with InstallManager
        // This is a basic installer
        $this->call('migrate', ['--force' => true]);

        // Set 'installed' config
        ConfigManager::setConfigs(['app.installed' => TRUE]);

        // Create the admin user. When bypassing prompts (e.g. CI) use defaults,
        // otherwise prompt the installer for the account details.
        if (User::count() === 0) {
            $this->createAdminUser($adminUser);
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

    /**
     * Interactively prompt the installer for the admin account details,
     * re-prompting on each field until valid input is provided.
     *
     * @return array{name: string, username: string, email: string, password: string}
     */
    protected function promptForAdminUser(): array
    {
        $this->info('Create the administrator account:');

        $rules    = $this->adminUserValidationRules();
        $name     = $this->askValidated('name', 'Name', $rules['name']);
        $username = $this->askValidated('username', 'Username', $rules['username']);
        $email    = $this->askValidated('email', 'Email address', $rules['email']);

        $this->line('Password requirements: at least 10 characters, including letters and numbers.');
        $password = $this->askValidatedPassword();

        return [
            'name'     => $name,
            'username' => $username,
            'email'    => $email,
            'password' => $password,
        ];
    }

    /**
     * The validation rules applied to the admin account prompts. These mirror
     * the web installer (App\Http\Controllers\Admin\InstallController).
     *
     * @return array<string, array<int, mixed>>
     */
    public function adminUserValidationRules(): array
    {
        return [
            'name'     => ['required'],
            'username' => ['required', 'min:8', 'alpha_dash'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * Ask for a single field, re-prompting until it passes the given rules.
     *
     * @param  array<int, mixed>  $rules
     */
    protected function askValidated(string $field, string $prompt, array $rules): string
    {
        while (true) {
            $value     = (string) $this->ask($prompt);
            $validator = Validator::make([$field => $value], [$field => $rules]);

            if ($validator->passes()) {
                return $value;
            }

            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
        }
    }

    /**
     * Ask for the password (hidden) and its confirmation, re-prompting until
     * the password meets the requirements and both entries match.
     */
    protected function askValidatedPassword(): string
    {
        while (true) {
            $password = (string) $this->secret('Password');
            $confirm  = (string) $this->secret('Confirm password');

            $validator = Validator::make([
                'password'              => $password,
                'password_confirmation' => $confirm,
            ], [
                'password' => $this->adminUserValidationRules()['password'],
            ]);

            if ($validator->passes()) {
                return $password;
            }

            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
        }
    }

    /**
     * Persist the admin user with full (100) access level.
     *
     * @param  array{name: string, username: string, email: string, password: string}  $data
     */
    protected function createAdminUser(array $data): void
    {
        $User           = new User;
        $User->name     = $data['name'];
        $User->username = $data['username'];
        $User->email    = $data['email'];
        $User->password = bcrypt($data['password']);
        $User->save();

        $User->access_level = 100;
        $User->save();
    }
}
