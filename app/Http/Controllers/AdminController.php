<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

// Controller for views only accessible by Administrative users (access_level >= 100)

class AdminController extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->middleware(['auth:100', 'migrate']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function getMain()
    {
        return view('admin.main');
    }

    public function todo() {
        return view('admin.todo');
    }

    public function help() {
        return view('admin.help');
    }

    public function softwareUpdate(Request $request) {
        $local_version    = \App\Engine::getHardcodedVersion();
        $upstream_version = \App\Engine::getUpstreamVersion(TRUE);

        $needs_update     = $upstream_version ? version_compare($local_version, $upstream_version->version, '<') : FALSE;

        $vars = [
            'local'         => $local_version,
            'upstream'      => $upstream_version ? $upstream_version->version : '(unknown)',
            'update'        => $needs_update,
            'php_update'    => FALSE,
            'php_local'     => NULL,
            'php_min'       => NULL,
            'show_info'     => $request->input('info'),
        ];

        if($needs_update && $upstream_version->php_error) {
            $vars['php_update'] = TRUE;
            $vars['php_local'] = $upstream_version->local_php_version;
            $vars['php_min']   = $upstream_version->php_required_min;
        }

        return view('admin.update', $vars);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
