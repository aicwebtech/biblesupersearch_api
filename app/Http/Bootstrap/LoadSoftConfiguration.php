<?php

namespace App\Http\Bootstrap;

use Exception;
use Illuminate\Config\Repository;
use Symfony\Component\Finder\Finder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use App\Models\Config;
use DB;

class LoadSoftConfiguration {

    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app) {

        // First we will see if we have a cache configuration file. If we do, skip loading soft configs
        // here, because the soft configs are already cached into it, and would have already been loaded
        if (file_exists($cached = $app->getCachedConfigPath())) {
//            var_dump('cached, skipping');
            return;
        }

        try{
//            var_dump(config('app.name'));

            // Load soft configs, organize them into key-value pairs
            $Configs = Config::all();
            $config_values = [];

            foreach($Configs as $Config) {
                $config_values[$Config->key] = $Config->value;
            }

//            var_dump($config_values);

            // Set soft configs as application configs
            config($config_values);

//            var_dump(config('app.name'));

        }
        catch (Exception $ex) {
            // DB Error, Do nothing
        }

    }
}
