<?php

namespace App\Http\Bootstrap;

use Exception;
use Illuminate\Config\Repository;
use Symfony\Component\Finder\Finder;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use App\Models\Config;
use App\ConfigManager;
use App\Helpers;
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

            $mysql_version = $this->getMysqlVersion();

            $config_values['database.mysql.server_version'] = $mysql_version;
            $config_values['database.mysql.new_regexp'] = version_compare($mysql_version, '8.0.4', '>=');

            $config_values['app.premium'] = Helpers::isPremium();

            // Set global soft configs as application configs
            config($config_values);
        }
        catch (Exception $ex) {
            // DB Error, Do nothing
        }

    }

    private function getMysqlVersion() {
        $pdo = DB::connection()->getPdo();
        $version = $pdo->query('select version()')->fetchColumn();
        preg_match("/^[0-9\.]+/", $version, $match);

        $version = $match[0];
        return $version;
    }
}
