<?php

namespace aicwebtech\BibleSuperSearch\Http\Bootstrap;

use Exception;
use Illuminate\Config\Repository;
use Symfony\Component\Finder\Finder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use aicwebtech\BibleSuperSearch\Models\Config;
use aicwebtech\BibleSuperSearch\ConfigManager;
use aicwebtech\BibleSuperSearch\Helpers;
use DB;

class LoadSoftConfiguration {

    /**
     * Bootstrap the given application.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app) {
        // TODO - Load user specific configs (these are NOT cached)

        // First we will see if we have a cache configuration file. If we do, skip loading soft configs
        // here, because the soft configs are already cached into it, and would have already been loaded
        if (file_exists($cached = $app->getCachedConfigPath())) {
            return;
        }

        try {
            // Load global soft configs
            $config_values = ConfigManager::getGlobalConfigs();

            // Set any other preset config values here
            $config_values['app.premium'] = Helpers::isPremium();

            // Set global soft configs as application configs
            config($config_values);
        }
        catch (Exception $ex) {
            // DB Error, Do nothing
        }

    }
}
