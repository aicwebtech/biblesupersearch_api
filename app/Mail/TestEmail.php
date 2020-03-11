<?php

namespace aicwebtech\BibleSuperSearch\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TestEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $email_address;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('test.email')
                    ->to($this->email_address)
                    ->with([
                        'email_address' => $this->email_address,
                        'server_location' => $this->getServerUrl()
                    ]);
    }

    protected function getServerUrl() {
        $http   = (array_key_exists('HTTPS', $_SERVER) && !empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
        $server = (array_key_exists('SERVER_NAME', $_SERVER) && !empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'biblesupersearch.com';
        $server = $http . $server;
        return $server;
    }
}
