<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Engine;

class DocumentationController extends Controller {
    public function __invoke() {
        $Engine = new Engine();
        $bibles = $Engine->actionBibles( ['order_by_lang_name' => TRUE] );
        return view('docs.home', ['bibles' => $bibles]);
    }
}

