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
    public function handle($request, Closure $next) 
    {

        $err  = NULL;
        $code = NULL;
        $key = $request->input('key') ?: null;
        $uri = $request->path();
        $parts = explode('/', $uri);
        $action = isset($parts[1]) ? $parts[1] : 'query';
        $ApiKey = null;
        $key_id = null;

        if(!config('app.installed')) {
            $err = 'errors.app_not_installed';
            $code = 500;
        }

        if(config('app.experimental') && !$err && $key) {
            // keyed access - look up key

            $ApiKey = ApiKey::findByKey($key);

            if(!$ApiKey) {
                // Key not found - no access granted
                $err = 'errors.access_revoked';
            } else {
                $key_id = $ApiKey->id;
            }
        }
        
        if(!$err) {        
            // look up IP record / keyed AND keyless access          
            
            // $host = (array_key_exists('HTTP_REFERER', $_SERVER)) ? $_SERVER['HTTP_REFERER'] : 'localhost';
            // $ip   = (array_key_exists('REMOTE_ADDR', $_SERVER))  ? $_SERVER['REMOTE_ADDR']  : '127.0.0.1';                
            $IP = IpAccess::findOrCreateByIpOrDomain(true);

            $Access = $ApiKey ?: $IP;

            if(!$Access->incrementDailyHits()) {
                if($Access->isAccessRevoked()) {
                    $err  = 'errors.access_revoked';
                    $code = 403;
                }
                else {
                    $err  = 'errors.hit_limit_reached';
                    $code = 429;
                }
            }

            if(!$err && !$Access->accessLevel->hasActionAccess($action)) {
                $err  = 'errors.action.not_allowed';
                $code = 403;
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
