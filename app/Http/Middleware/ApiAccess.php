<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\IpAccess;

/*
 * Ensures users / websites are not using the API excessively
 */

class ApiAccess
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
        $IP = IpAccess::findOrCreateByIpOrDomain($_SERVER['REMOTE_ADDR'], $_SERVER['REMOTE_HOST']);
        $IP->incrementDailyHits();
        
        return $next($request);
    }
}
