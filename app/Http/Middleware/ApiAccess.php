<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\IpAccess;
use App\Models\ApiKey;
use App\ApiAccessManager;
use App\Helpers;
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
        // $key = $request->input('key') ?: null;
        // $dom = ApiAccessManager::trustedDomain(); // never trust a client-supplied domain - see ApiAccessManager
        // Versioned or not, the action is what decides the rate limit and the access-level
        // check. SetCacheHeaders resolves the same thing for its own decision, so the parsing
        // lives in one place - see Helpers::resolveApiAction().
        $action = Helpers::resolveApiAction($request->path()) ?? Helpers::DEFAULT_API_ACTION;

        $billable = static::isBillableRequest($request->path());

        $Access = null;
        $key_id = null;

        if(!config('app.installed')) {
            $err = 'errors.app_not_installed';
            $code = 500;
        }

        if(!$err) {
            $Access = ApiAccessManager::lookUp($request);

            if(!$Access || $Access->isAccessRevoked()) {
                // Key not found - no access granted
                $err  = 'errors.access_revoked';
                $code = 403;
            } else if($billable && !$Access->incrementDailyHits()) {
                $err  = 'errors.hit_limit_reached';
                $code = 429;
            }

            if(!$err && !$Access->accessLevel->hasActionAccess($action)) {
                $err  = 'errors.action.not_allowed';
                $code = 403;
            }
        }

        /*
        if(config('app.experimental') && !$err && $key) {
            // keyed access - look up key
            $Access = ApiKey::findByKey($key);

            if(!$Access || $Access->isAccessRevoked()) {
                // Key not found - no access granted
                $err  = 'errors.access_revoked';
                $code = 403;
            } else {
                $key_id = $Access->id;
            }
        }
        
        if(!$err) {        
            // look up IP record for keyless access                       
            $Access = $Access ?: IpAccess::findOrCreateByIpOrDomain(true, $dom);

            if($Access->isAccessRevoked()) {
                $err  = 'errors.access_revoked';
                $code = 403;
            } else if(!in_array($action, config('bss.free_actions')) && !$Access->incrementDailyHits()) {
                $err  = 'errors.hit_limit_reached';
                $code = 429;
            }

            if(!$err && !$Access->accessLevel->hasActionAccess($action)) {
                $err  = 'errors.action.not_allowed';
                $code = 403;
            }
        }
        */
        
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

    /**
     * Whether a request spends one of the caller's daily hits.
     *
     * Two things make a request free. The actions in bss.free_actions are free by
     * configuration - asking how much quota is left must not spend any of it. A version this
     * application does not serve is free because it is never answered: the route accepts any
     * digits, but ApiController::versionedAction() replies 404 or 410 and no engine runs.
     * Charging for that would let a client hardcoded to a retired prefix spend its whole
     * allowance on errors and then be rate limited out of its real traffic.
     *
     * A path naming no version at all is the legacy unversioned route, which is served, so it
     * stays billable exactly as it was before the versioned route existed.
     *
     * @param string|null $path Request path, as Request::path() returns it - no leading slash
     */
    public static function isBillableRequest(?string $path): bool
    {
        $version = Helpers::resolveApiVersion($path);

        if($version !== NULL && !in_array($version, config('app.api_version_list'))) {
            return FALSE;
        }

        $action = Helpers::resolveApiAction($path) ?? Helpers::DEFAULT_API_ACTION;

        return !in_array($action, config('bss.free_actions'));
    }
}
