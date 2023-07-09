<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class UserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:password {username} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates a user\'s password.';

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
        $User = new \App\User;

        $User = \App\User::where('username', $this->argument('username'))->firstOrFail();
        $User->password = password_hash( $this->argument('password'), PASSWORD_BCRYPT);
        $User->save();
    }
}
