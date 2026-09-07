<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\InstallManager as Installer;
use Validator;

class InstallController extends Controller 
{
    protected $redirectTo = '/install/config';

    public function __construct() 
    {
        parent::__construct();
        $this->middleware('installed');
    }

    /**
     * Step 1: Display a simple confirm form to begin the install process
     * @return type
     */
    public function index() 
    {
        return view('install.index');
    }

    /**
     * Step 2: Check dependencies, required hard configs, and database connection
     */
    public function check() 
    {
        list($checklist, $success) = Installer::checkSettings();

        return view('install.check', [
            'checklist' => $checklist,
            'success'   => $success,
        ]);
    }

    /**
     * Step 3: Gather required soft configs
     */
    public function config() 
    {
        return view('install.config');
    }

    public function handleConfig(Request $request) 
    {

        $validator = Validator::make($request->all(), [
            'name'      => 'required',
            'username'  => 'required|min:8|alpha_dash',
            'email'     => 'required|email',
            'password'  => ['required', \Illuminate\Validation\Rules\Password::defaults()],
            'password2' => 'required|same:password',
        ], [
            'password2.same' => 'The two passwords do not match'
        ]);

        if($validator->fails()) {
            return view('install.config', [
                'input'  => $request->all(),
                'errors' => $validator->errors(),
            ]);
        }
        else {
            return $this->install($request);
        }
    }

    /**
     * Step 4: Using provided hard and soft configs, install application to database
     *
     * A failed install used to fall out of an empty else branch and return a blank HTTP 200, so
     * the three ways it can fail are now reported apart from one another.
     */
    public function install(Request $request) 
    {
        $result = Installer::install($request);

        if($result === Installer::INSTALL_SUCCESS) {
            return view('install.done');
        }

        return $this->installError($result);
    }

    /**
     * Renders the reason an install did not happen.
     *
     * @param  string $result one of the Installer::INSTALL_* codes
     */
    protected function installError(string $result) 
    {
        $messages = [
            Installer::INSTALL_ALREADY_INSTALLED => 'This application is already installed.',
            Installer::INSTALL_IN_PROGRESS       => 'An installation is already running. Wait for it to finish, then reload this page.',
            Installer::INSTALL_FAILED            => 'The installation could not be completed. See the application log for the details.',
        ];

        $message = array_key_exists($result, $messages) ? $messages[$result] : $messages[Installer::INSTALL_FAILED];
        $status  = ($result === Installer::INSTALL_FAILED) ? 500 : 409;

        return response()->view('install.error', [
            'message' => $message,
            'retry'   => ($result !== Installer::INSTALL_ALREADY_INSTALLED),
        ], $status);
    }

    /**
     * Step 5??: Demonstrate / test the installed software
     */
    public function demo() 
    {

    }
}
