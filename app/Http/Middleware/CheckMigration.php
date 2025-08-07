<?php

namespace App\Http\Middleware;

use Closure;
use Artisan;

use \App\ConfigManager;

class CheckMigration 
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) 
    {
        $using_cache = config('app.config_cache');
        // todo this needs to check for any update then perform any needed tasks

        $cur_version  = \App\Engine::getHardcodedVersion();
        $prev_version = config('app.version_cache');

        if($cur_version != $prev_version) {
            Artisan::call('view:clear'); // Force cached view templates to clear out because HTML may have changed
            $this->_migrateIfNeeded();
            ConfigManager::setConfig('app.version_cache', $cur_version);

            if($using_cache) {
                Artisan::call('config:cache');
            }
        }
        else {
            $this->_migrateIfNeeded(); // Showuld we continue to migrate even if version # hasn't changed?
        }

        return $next($request);
    }    

    private function _migrateIfNeeded($pretend = FALSE) 
    {
        if($this->_migrationNeeded()) {
            $this->_migrate($pretend);
        }
    }

    private function _migrate($pretend = FALSE) 
    {
        Artisan::call('view:clear'); // Force cached view templates to clear out because HTML may have changed
        return Artisan::call('migrate', ['--force' => TRUE, '--pretend' => $pretend]);
        // return Artisan::call('migrate', ['--seed' => TRUE, '--force' => TRUE, '--pretend' => $pretend]);
    }

    private function _migrationNeeded() 
    {
        $outCode = Artisan::call('migrate:status');
        $output = Artisan::output();
        $output_array = explode("\n", $output);
        $needed = FALSE;

        foreach($output_array as $row) {
            if(
                preg_match('/\s*Pending\s*/', $row) ||
                preg_match('/\|\s*No\s*\|/', $row) // legacy
            ) {
                $needed = TRUE;
                break;
            }
        }

        return $needed;
    }
}
