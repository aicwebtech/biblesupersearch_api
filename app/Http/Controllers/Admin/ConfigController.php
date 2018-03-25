<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\ConfigManager;
use Illuminate\Support\Facades\Artisan;

class ConfigController extends Controller
{
    public function __construct() {
        parent::__construct();
        $this->middleware('auth:100');
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

        return view('admin.config', [
            'configs' => $config_values,
            'bibles'  => $Bibles,
            'hl_tags' => ['b', 'em', 'strong'],
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
    public function destroy()
    {
        //


    }
}
