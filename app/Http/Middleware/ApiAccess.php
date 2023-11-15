<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\IpAccess;
use App\Models\ApiKey;
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

        $err  = NULL;
        $code = NULL;

        if(!config('app.installed')) {
            $err = 'errors.app_not_installed';
            $code = 500;
        }
        
        if(!$err) {        
            $key = $request->input('key') ?: null;

            if($key) {
                // keyed access

                $ApiKey = ApiKey::findByKey($key);

                if(!$ApiKey) {
                    // Key not found - no access granted
                    $err = 'errors.app_not_installed';
                } else {

                }

                // if(!$ApiKey->incrementDailyHits()) {
                //     if($ApiKey->isAccessRevoked()) {
                //         $err  = 'errors.access_revoked';
                //         $code = 403;
                //     }
                //     else {
                //         $err  = 'errors.hit_limit_reached';
                //         $code = 500;
                //     }
                // }
            } else {
                // keyless access            
                
                $host = (array_key_exists('HTTP_REFERER', $_SERVER)) ? $_SERVER['HTTP_REFERER'] : 'localhost';
                $ip   = (array_key_exists('REMOTE_ADDR', $_SERVER))  ? $_SERVER['REMOTE_ADDR']  : '127.0.0.1';                
                $IP = IpAccess::findOrCreateByIpOrDomain($ip, $host);

                if(!$IP->incrementDailyHits()) {
                    if($IP->isAccessRevoked()) {
                        $err  = 'errors.access_revoked';
                        $code = 403;
                    }
                    else {
                        $err  = 'errors.hit_limit_reached';
                        $code = 500;
                    }
                }
            }

        }
        
        if($err) {            
            $response = new \stdClass;
            $response->errors = array(trans($err));
            $response->error_level = 4;

            return (new Response(json_encode($response), $code))
                -> header('Content-Type', 'application/json; charset=utf-8')
                -> header('Access-Control-Allow-Origin', '*');
        }

        return $next($request);
    }
}
