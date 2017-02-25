<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\IpAccess;
use Illuminate\Http\Response;

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
    public function handle($request, Closure $next) {
        $host = (array_key_exists('HTTP_REFERER', $_SERVER)) ? $_SERVER['HTTP_REFERER'] : NULL;
        $ip = gethostbyname($host);
        $ip = ($ip == $host) ? $_SERVER['REMOTE_ADDR'] : $ip;
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, $host);
        
        if(!$IP->incrementDailyHits()) {
            $response = new \stdClass;
            $response->errors = array(trans('errors.hit_limit_reached'));
            
            return (new Response(json_encode($response), 500))
            -> header('Content-Type', 'application/json; charset=utf-8')
            -> header('Access-Control-Allow-Origin', '*');
        }
        
        return $next($request);
    }
}
