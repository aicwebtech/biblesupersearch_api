<?php

namespace aicwebtech\BibleSuperSearch\Http\Middleware;

use Closure;
use aicwebtech\BibleSuperSearch\InstallManager;

class InstalledRedirect
{
    /**
     * Handle an incoming request.
     * Redirects from installer to docs page if app IS installed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if (InstallManager::isInstalled()) {
            return redirect(route('docs'));
        }

        return $next($request);
    }
}
