<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Password;

class PasswordController extends Controller
{
    protected $redirectTo = '/auth/success';

    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords, SendsPasswordResetEmails {
        ResetsPasswords::broker insteadof SendsPasswordResetEmails;
    }

    /**
     * Create a new password controller instance.
     *
     * @return void
     */
    public function __construct() {
        $this->middleware('guest');
    }

    /**
     * Display the password reset view for the given token.
     *
     * If no token is present, display the link request form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $token
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     *
     * OVERRIDING THIS BECAUSE SOMETHING WRONG W ROUTE, NOT GETTING TOKEN
     */
    public function showResetForm(Request $request, $token = null) {
        $data = $request->toArray();

        if(!$token && count($data) == 1) {
            $keys  = array_keys($data);
            $token = array_shift($keys);
        }

        return view('auth.passwords.reset')->with(
            ['token' => $token, 'email' => $request->email]
        );
    }

    public function success() {
        return view('auth.passwords.success');
    }

    /**
     * Reset the given user's password.
     *
     * @param  \Illuminate\Contracts\Auth\CanResetPassword  $user
     * @param  string  $password
     * @return void
     *
     * OVERRIDING THIS SO THE USER ISN'T AUTOMATICALLY LOGGED IN
     */
    protected function resetPassword($user, $password)
    {
        $user->forceFill([
            'password' => bcrypt($password),
            'remember_token' => Str::random(60),
        ])->save();

//        $this->guard()->login($user);
    }


    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function _sendResetLinkEmail(Request $request)
    {
//        return 'bacon';

        $this->validateEmail($request);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.

        $broker = $this->broker();

        $response = $broker->sendResetLink($request->only('email'), function (Message $message) {
            $message->subject($this->getEmailSubject());
                $message->from('baconman@example.com', 'You can be a big pig too');
//            $message->from(env('MAIL_FROM'), env('APP_NAME'));
                throw new Exception('farquad was here');
        });

//        $response = $this->broker()->sendResetLink(
//            $request->only('email'),
//            function(Message $message) {
//                $message->subject($this->getEmailSubject());
////                $message->from(config('mail.from.address'), config('mail.from.name'));
//                $message->from('baconman@example.com', 'You can be a big pig too');
//                $message->from('baconman@example.com', 'You can be a big pig too');
//                die('dead');
//            }
//        );

        return $response == Password::RESET_LINK_SENT
                    ? $this->sendResetLinkResponse($response)
                    : $this->sendResetLinkFailedResponse($request, $response);
    }

    protected function resetEmailBuilder()
    {
        return function (Message $message) {
            $message->subject($this->getEmailSubject());
            $message->from('baconman@bacon.com', 'You can be a big pig too');
            throw new Exception('farquad was here');
//            $message->from('you@email.com', 'you');
        };
    }
}
