<?php

namespace App;

use App\Models\IpAccess;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use App\Interfaces\AccessLogInterface;

class ApiAccessManager
{
    public static function lookUp(Request $request): AccessLogInterface
    {
        $key = $request->input('key') ?: null;
        $dom = $request->input('domain') ?: null;
        return static::lookUpHelper($key, $dom);
    }

    public static function lookUpByInput($input): AccessLogInterface
    {
        $key = isset($input['key']) ? $input['key'] : null;
        $dom = isset($input['domain']) ? $input['domain'] : null;
        return static::lookUpHelper($key, $dom);
    }

    protected static function lookUpHelper($key, $dom): AccessLogInterface
    {
        $err  = NULL;
        $code = NULL;
        $Access = null;

        if(config('app.experimental') && !$err && $key) {
            // keyed access - look up key
            $Access = ApiKey::findByKey($key);

            if(!$Access || $Access->isAccessRevoked()) {
                // Key not found - no access granted
                $err  = true;
            }
        }
        
        if(!$err) {        
            // look up IP/domain record for keyless access                       
            $Access = $Access ?: IpAccess::findOrCreateByIpOrDomain(true, $dom);
        }

        return $Access ?: false;
    }
}