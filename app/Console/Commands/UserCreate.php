<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class UserCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {email_address} {username} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creats a new user.';

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
        $User->email    = $this->argument('email_address');
        $User->username = $this->argument('username');
        $User->password = password_hash( $this->argument('password'), PASSWORD_BCRYPT);
        $User->save();
    }
}
