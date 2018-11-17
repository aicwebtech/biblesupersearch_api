<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Engine;
use App\Models\Post;

class DocumentationController extends Controller {
    public function __construct() {
        parent::__construct();
        $this->middleware(['install','https']);
    }

    public function __invoke() {
        $Engine = new Engine();
        $bibles = $Engine->actionBibles( ['order_by_lang_name' => TRUE] );
        $TOS = Post::where('key', 'tos')->firstOrNew([]);
        $Privacy = Post::where('key', 'privacy')->firstOrNew([]);

        return view('docs.home', [
            'bibles'    => $bibles,
            'TOS'       => $TOS,
            'Privacy'   => $Privacy,
        ]);
    }
}

