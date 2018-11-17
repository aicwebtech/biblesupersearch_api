<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\InstallManager as Installer;

class InstallController extends Controller {

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

    }

    /**
     * Step 4: Using provided hard and soft configs, install application to database
     */
    public function install() {

    }

    /**
     * Step 5??: Demonstrate / test the installed software
     */
    public function demo() {

    }
}
