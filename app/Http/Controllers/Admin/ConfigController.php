<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\ConfigManager;
use Illuminate\Support\Facades\Artisan;
use App\Http\Responses\Response;
use Illuminate\Support\Arr;

class ConfigController extends Controller
{
    public function __construct() 
    {
        parent::__construct();
        $this->middleware('auth:100');
        $this->middleware('migrate')->only('index');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() 
    {
        $config_values = ConfigManager::getGlobalConfigs();
        $Bibles = \App\Models\Bible::where('enabled', 1)->where('installed', 1)->get();
        $render_writeable = \App\RenderManager::isRenderWritable();
        $render_dir = base_path('bibles/rendered');

        if(!$render_writeable) {
            ConfigManager::setConfig('download.enable', FALSE);
            $config_values['download.enable'] = FALSE;
        }

        $st_lang = array_filter(config('bss.search_types'), function($st) {
            return (bool)$st['multi_lang'];
        });

        return view('admin.config', [
            'configs'                   => $config_values,
            'bibles'                    => $Bibles,
            'hl_tags'                   => ['b', 'em', 'strong'],
            'rendered_space'            => \App\RenderManager::getUsedSpace(),
            'render_writeable'          => $render_writeable,
            'render_dir'                => $render_dir,
            'search_type_multi_lang'    => $st_lang,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) 
    {
        $data = $request->toArray();
        ConfigManager::setGlobalConfigs($data);

        $config_cache = config('app.config_cache', '0');
        $config_cache_changed = array_key_exists('app__config_cache', $data) && $data['app__config_cache'] != $config_cache;

        if($config_cache_changed) {
            if($data['app__config_cache']) {
                Artisan::call('config:cache');
            } else {
                Artisan::call('config:clear');
            }
        }

        // Workaround for the fact that Laravel's config cache does not update the APP_URL env var at runtime
        // The URL is briefly set to whats in the .env file (as opposed to our soft config)
        // If the soft config URL is different than the .env file, then we redirect back to the config page
        if(config('app.url') != env('APP_URL')) {
            return redirect()->back();
        }

        return redirect('admin/config');
    }

    /**
     * Reset global configs to default
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy() 
    {
        // to do
    }

    public function cleanUpDownloadFiles() 
    {
        \App\RenderManager::cleanUpTempFiles();
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->space_used = \App\RenderManager::getUsedSpace();
        return new Response($resp, 200);
    }

    public function deleteAllDownloadFiles() 
    {
        \App\RenderManager::deleteAllFiles();
        $resp = new \stdClass();
        $resp->success = TRUE;
        $resp->space_used = \App\RenderManager::getUsedSpace();
        return new Response($resp, 200);
    }
}
