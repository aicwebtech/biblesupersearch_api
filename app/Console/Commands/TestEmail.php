<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\TestEmail as Mailer;

class TestEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:email {email_address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tests email configuration.  Will attempt to send an email to the specified address.';

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
        $Email = new Mailer();
        $Email->email_address = $this->argument('email_address');
        Mail::send($Email);
    }
}
