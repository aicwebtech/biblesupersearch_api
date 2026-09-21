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
        ResetsPasswords::credentials insteadof SendsPasswordResetEmails;
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
     */
    public function showResetForm(Request $request, $token = null) {
        return view('auth.passwords.reset')->with(
            ['token' => $token, 'email' => $request->query('email')]
        );
    }

    public function success() {
        return view('auth.passwords.success');
    }

    /**
     * Get the password reset validation rules.
     *
     * Enforces the same strength policy as registration / install.
     *
     * @return array
     */
    protected function rules()
    {
        return [
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ];
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
    public function sendResetLinkEmail(Request $request)
    {
        $this->validateEmail($request);

        // Send the link, but deliberately ignore the broker's return value:
        // distinguishing RESET_LINK_SENT from INVALID_USER would tell an
        // anonymous requester whether an address has an account here.
        $this->broker()->sendResetLink($request->only('email'));

        return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
    }

    /**
     * Never surface a broker failure to the requester.
     *
     * Overridden so that a failure inside the framework's own flow cannot
     * reintroduce the enumeration difference.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendResetLinkFailedResponse(Request $request, $response)
    {
        return $this->sendResetLinkResponse($request, Password::RESET_LINK_SENT);
    }

    /**
     * Validate the email for the given request.
     *
     * @param \Illuminate\Http\Request  $request
     * @return void
     */
    protected function validateEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);
    }
}
