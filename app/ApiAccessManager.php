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

    public static function isWhitelisted($ip = null, $domain = null)
    {
        $whitelist = config('bss.daily_access_whitelist');

        if(!$whitelist || !$ip && !$domain) {
            return false;
        }

        $items = explode("\n", str_replace(["\r\n", "\r"], "\n", $whitelist));
        
        foreach($items as &$i) {
            $i = self::parseDomain($i);
        }
        unset($i);

        if($ip && in_array($ip, $items) || $domain && in_array($domain, $items)) {
            return true;
        }

        return false;
    }

    static public function parseDomain($host) 
    {
        if(empty($host)) {
            return null;
        }

        $host = str_replace(array('http:','https:'), '', $host);
        $host = trim($host);
        $host = trim($host, '/');
        $pieces = explode('/', $host);
        $domain = $pieces[0];

        if(strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }

        $col_pos = strpos($domain, ':');

        if($col_pos !== FALSE) {
            $domain = substr($domain, 0, $col_pos);
        }

        $hash_pos = strpos($domain, '#');

        if($hash_pos !== FALSE) {
            $domain = substr($domain, 0, $hash_pos);
        }

        if($domain == 'localhost') {
            return null;
        }

        return $domain;
    }
}