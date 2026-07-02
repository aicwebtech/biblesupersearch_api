<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

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
        $password = $this->argument('password');

        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::defaults()]]
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('password'));
            return self::FAILURE;
        }

        $User = new \App\User;
        $User->name     = $this->argument('username');
        $User->email    = $this->argument('email_address');
        $User->username = $this->argument('username');
        $User->password = password_hash($password, PASSWORD_BCRYPT);
        $User->save();

        return self::SUCCESS;
    }
}
