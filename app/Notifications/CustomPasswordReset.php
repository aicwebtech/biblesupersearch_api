<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\User;


class CustomPasswordReset extends Notification
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    protected $User;

    /**
     * Create a notification instance.
     *
     * @param  string  $token
     * @return void
     */
    public function __construct(User $User, $token)
    {
        $this->token = $token;
        $this->User = $User;
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $data = [
            'url' => url(config('app.url').route('password.reset', $this->token, false)),
            'User' => $this->User,
        ];

        return (new MailMessage)
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset Password', url(config('app.url').route('password.reset', $this->token, false)))
            ->line('If you did not request a password reset, no further action is required.')
            ->greeting('Hello, ' . $this->User->name . ' ( ' . $this->User->username . ' )')
            ->view('mail.pwreset', $data) // Giving up on nice, prepackaged, buggy template for now and hacking out my own
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Bible SuperSearch - Password Reset');
    }

      /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}

