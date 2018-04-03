<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Post;

class PostConfigController extends Controller
{
    public function tos() {
        $Post = Post::where('key', 'tos')->firstOrFail();

        return view('admin.postconfig', [
            'Post' => $Post,
        ]);
    }

    public function saveTos(Request $request) {
        $Post = Post::where('key', 'tos')->firstOrFail();
        $Post->content = $request->input('content');
        $Post->save();
        return redirect('/admin/tos');
    }

    public function privacy() {
        $Post = Post::where('key', 'privacy')->firstOrFail();

        return view('admin.postconfig', [
            'Post' => $Post,
        ]);
    }

    public function savePrivacy(Request $request) {
        $Post = Post::where('key', 'privacy')->firstOrFail();
        $Post->content = $request->input('content');
        $Post->save();
        return redirect('/admin/privacy');
    }
}
