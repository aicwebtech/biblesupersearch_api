<?php

namespace aicwebtech\BibleSuperSearch\Http\Controllers\Admin;

use Illuminate\Http\Request;
use aicwebtech\BibleSuperSearch\Http\Controllers\Controller;
use aicwebtech\BibleSuperSearch\ConfigManager;
use Illuminate\Support\Facades\Artisan;
use aicwebtech\BibleSuperSearch\Http\Responses\Response;

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
        $Bibles = \aicwebtech\BibleSuperSearch\Models\Bible::where('enabled', 1)->where('installed', 1)->get();
        $render_writeable = \aicwebtech\BibleSuperSearch\RenderManager::isRenderWritable();
        $render_dir = base_path('bibles/rendered');

        if(!$render_writeable) {
            ConfigManager::setConfig('download.enable', FALSE);
            $config_values['download.enable'] = FALSE;
        }

        return view('admin.config', [
            'configs'           => $config_values,
            'bibles'            => $Bibles,
            'hl_tags'           => ['b', 'em', 'strong'],
            'rendered_space'    => \aicwebtech\BibleSuperSearch\RenderManager::getUsedSpace(),
            'render_writeable'  => $render_writeable,
            'render_dir'        => $render_dir,
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
        \aicwebtech\BibleSuperSearch\RenderManager::cleanUpTempFiles();
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->space_used = \aicwebtech\BibleSuperSearch\RenderManager::getUsedSpace();
        return new Response($resp, 200);
    }

    public function deleteAllDownloadFiles() {
        \aicwebtech\BibleSuperSearch\RenderManager::deleteAllFiles(TRUE);
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->space_used = \aicwebtech\BibleSuperSearch\RenderManager::getUsedSpace();
        return new Response($resp, 200);
    }
}
