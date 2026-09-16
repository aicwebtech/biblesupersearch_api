<?php

namespace App\Http\Middleware;

use Closure;

class HttpsRedirect {
    /**
     * Handle an incoming request.
     *
     * This middleware is global (app/Http/Kernel.php), so it covers /api/* as
     * well as the browser surfaces. A 302 tells the client to re-issue the
     * request as a GET, which would silently drop the body of an API POST, so
     * unsafe methods get 307 -- the method- and body-preserving equivalent of
     * a 302. Safe methods keep 302 so that toggling REDIRECT_HTTPS back off is
     * not defeated by a cached permanent redirect.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) 
    {
        if (!$request->secure() && config('app.redirect_https') === TRUE) {
            $status = $request->isMethodSafe() ? 302 : 307;

            return redirect()->secure($request->getRequestUri(), $status);
        }

        return $next($request);
    }
}
