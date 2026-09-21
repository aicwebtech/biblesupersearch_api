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
     * Retry is offered for everything except an application that is genuinely already
     * installed: the other three describe conditions the operator can clear.
     *
     * @param  string $result one of the Installer::INSTALL_* codes
     */
    protected function installError(string $result) 
    {
        $messages = [
            Installer::INSTALL_ALREADY_INSTALLED => 'This application is already installed.',
            // Deliberately not the message above. Here the application is not installed and
            // nothing else on the site works, so telling the operator it is already installed
            // would send them looking for a working site that does not exist.
            Installer::INSTALL_NOT_FRESH         => 'This database already contains user accounts, so it is not a fresh installation. If it is the right database, restore its app.installed configuration value; otherwise point the application at an empty database. See the application log for the details.',
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
