<?php

namespace App\Http\Controllers\Auth;

use App\User;
use Validator;
use Auth;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;


class AuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesUsers, RegistersUsers {
        AuthenticatesUsers::redirectPath insteadof RegistersUsers;
        AuthenticatesUsers::guard insteadof RegistersUsers;
    }

    protected $redirectTo = '/landing';
    protected $loginPath = '/login';

    protected $landing_page = '/landing';

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest', ['except' => ['logout','landing']]);
        $this->middleware(['install', 'migrate']);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name'      => 'required|max:255',
            'email'     => 'required|email|max:255|unique:users',
            'password'  => 'required|confirmed|min:6',
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => bcrypt($data['password']),
        ]);
    }

    // This should force it to authenticate on username instead of email
    protected function username() {
        return 'username';
    }

    public function viewLogin() {
        return view('admin.login');
    }

    /**
     * Generic landing page accessible by ALL authenticated users
     * Will redirect based on user's role
     */
    public function landing() {
        if(!Auth::check()) {
            return redirect($this->loginPath);
        }

        return view('auth.landing');
    }

    /**
     * Attempt to log the user into the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function attemptLogin(Request $request)
    {
        $success = $this->guard()->attempt(
            $this->credentials($request), $request->boolean('remember')
        );

        // Determine if user exists
        $user = User::where($this->username(), $request->input( $this->username() ))->first();

        // Log the attempt
        $attempt = new \App\Models\LoginAttempt;
        $attempt->ip_address = $request->ip();
        $attempt->username = $request->input( $this->username() );
        $attempt->success = $success ? 1 : 0;
        $attempt->user_exists = $user ? 1 : 0;
        $attempt->save();       

        return $success;
    }

    /**
     * The user has been authenticated.
     * Redirect based on user's role
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user) {
        // Redirect based on user's role
        if(!$user) {
            return redirect($this->loginPath);
        }

        if($user->access_level >= 100) {
            return redirect('/admin');
        }

        return redirect($this->landing_page);
    }

}
