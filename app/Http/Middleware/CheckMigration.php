<?php

namespace App\Http\Middleware;

use Closure;
use Artisan;

class CheckMigration {

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $using_cache = config('app.config_cache');

        if($using_cache) {
            $soft_version = config('app.version');
            $hard_version = \App\Engine::getHardcodedVersion();

            if($soft_version != $hard_version) {
                $this->_migrateIfNeeded();
                Artisan::call('config:cache');
            }
        }
        else {
            $this->_migrateIfNeeded();
        }

        return $next($request);
    }

    private function _migrateIfNeeded($pretend = FALSE) {
        if($this->_migrationNeeded()) {
            $this->_migrate($pretend);
        }
    }

    private function _migrate($pretend = FALSE) {
        return Artisan::call('migrate', ['--seed' => TRUE, '--force' => TRUE, '--pretend' => $pretend]);
    }

    private function _migrationNeeded() {
        $outCode = Artisan::call('migrate:status');
        $output = Artisan::output();
        $output_array = explode("\n", $output);
        $needed = FALSE;

        foreach($output_array as $row) {
            if(preg_match('/\|\s*No\s*\|/', $row)) {
                $needed = TRUE;
                break;
            }
        }

        return $needed;
    }
}
