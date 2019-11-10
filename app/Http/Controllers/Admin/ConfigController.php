<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\ConfigManager;
use Illuminate\Support\Facades\Artisan;
use App\Http\Responses\Response;

class ConfigController extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->middleware('auth:100');
        $this->middleware('migrate')->only('index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {
        $config_values = ConfigManager::getGlobalConfigs();
        $Bibles = \App\Models\Bible::where('enabled', 1)->where('installed', 1)->get();

        return view('admin.config', [
            'configs'           => $config_values,
            'bibles'            => $Bibles,
            'hl_tags'           => ['b', 'em', 'strong'],
            'rendered_space'    => \App\RenderManager::getUsedSpace(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) {
        $data = $request->toArray();
        ConfigManager::setGlobalConfigs($data);

        if($data['app__config_cache']) {
            Artisan::call('config:cache');
        }
        else {
            Artisan::call('config:clear');
        }

        return redirect('admin/config');
    }

    /**
     * Reset global configs to default
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy() {
        // to do
    }

    public function cleanUpDownloadFiles() {
        \App\RenderManager::cleanUpTempFiles();
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->space_used = \App\RenderManager::getUsedSpace();
        return new Response($resp, 200);
    }

    public function deleteAllDownloadFiles() {
        \App\RenderManager::deleteAllFiles(TRUE);
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->space_used = \App\RenderManager::getUsedSpace();
        return new Response($resp, 200);
    }
}
