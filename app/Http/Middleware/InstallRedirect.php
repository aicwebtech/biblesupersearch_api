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
    public function handle($request, Closure $next) {
        if (!InstallManager::isInstalled()) {
            return redirect(route('admin.install'));
        }

        return $next($request);
    }
}
