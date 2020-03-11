<?php

namespace aicwebtech\BibleSuperSearch\Http\Controllers\Admin;

use Illuminate\Http\Request;
use aicwebtech\BibleSuperSearch\Http\Controllers\Controller;
use aicwebtech\BibleSuperSearch\InstallManager as Installer;
use Validator;

class InstallController extends Controller {
    protected $redirectTo = '/install/config';

    public function __construct() {
        parent::__construct();
        $this->middleware('installed');
    }

    /**
     * Step 1: Display a simple confirm form to begin the install process
     * @return type
     */
    public function index() {
        return view('install.index');
    }

    /**
     * Step 2: Check dependencies, required hard configs, and database connection
     */
    public function check() {
        list($checklist, $success) = Installer::checkSettings();

        return view('install.check', [
            'checklist' => $checklist,
            'success'   => $success,
        ]);
    }

    /**
     * Step 3: Gather required soft configs
     */
    public function config() {

        return view('install.config', [

        ]);
    }

    public function handleConfig(Request $request) {

        $validator = Validator::make($request->all(), [
            'name'      => 'required',
            'username'  => 'required|min:8|alpha_dash',
            'email'     => 'required|email',
            'password'  => 'required|min:8',
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
     */
    public function install(Request $request) {
        if(Installer::install($request)) {
            return view('install.done');
        }
        else {
            // error has happened...
        }
    }

    /**
     * Step 5??: Demonstrate / test the installed software
     */
    public function demo() {

    }
}
