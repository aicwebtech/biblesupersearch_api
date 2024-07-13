<?php

namespace App\Http\Middleware;

use Closure;
use App\InstallManager;

class InstallRedirect
{
    /**
     * Handle an incoming request.
     * Redirects to installer if app isn't installed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) 
    {
        if (!InstallManager::isInstalled()) {
            if(!InstallManager::modRewriteEnabled()) {
                die('<h1>Please enable mod_rewrite or equivalent on your web server before proceeding. (1)</h1>');
            }

            return redirect(route('admin.install'));
        }

        return $next($request);
    }
}
