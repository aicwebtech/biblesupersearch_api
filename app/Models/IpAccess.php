<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Interfaces\AccessLogInterface;
use App\ApiAccessManager;

class IpAccess extends Model implements AccessLogInterface 
{

    protected $table = 'ip_access';
    protected $fillable = ['ip_address','domain', 'limit'];

    protected $attributes = [
        'access_level_id' => null,
    ];

    public function __construct()
    {
        parent::__construct();

        $this->attributes['access_level_id'] = ApiAccessLevel::BASIC;
    }

    static public function findOrCreateByIpOrDomain($ip_address = null, $host = null) 
    {
        // When called with `true`, the IP and domain are derived from the
        // current request; an explicit IP/host comes from trusted server-side
        // code (admin tools, tests) and is always honored as-is.
        $from_request = ($ip_address === true);

        if($from_request) {
            // Trust only browser-set headers for the domain, never client-supplied
            // request parameters. See ApiAccessManager::trustedDomain().
            $default_host = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? 'localhost';
            $host = $host ?: $default_host;
            $ip_address = (array_key_exists('REMOTE_ADDR', $_SERVER))  ? $_SERVER['REMOTE_ADDR']  : '127.0.0.1';
        }

        $domain = ApiAccessManager::parseDomain($host);

        // A request-derived domain comes from the forgeable Origin/Referer
        // headers, so it only earns its own rate-limit bucket when it is
        // privileged (explicitly whitelisted, or the API's own host). Otherwise
        // a client could rotate the header to mint unlimited fresh daily quotas,
        // so such traffic is bucketed strictly by IP. See
        // ApiAccessManager::isDomainPrivileged().
        if($domain && (!$from_request || ApiAccessManager::isDomainPrivileged($domain))) {
            $IP = static::firstOrNew(['domain' => $domain]);

            if(!$IP->id) {
                $IP->ip_address = $ip_address;
                $IP->limit = ($ip_address == '127.0.0.1' || $ip_address == '::1') ? 0 : null;
                $IP->save();
            }
        }
        else {
            $IP = static::firstOrCreate(['ip_address' => $ip_address, 'domain' => null]);
        }

        return $IP;
    }

    static public function parseDomain($host) 
    {
        return ApiAccessManager::parseDomain($host);
    }

    public function accessLevel()
    {
        return $this->belongsTo(ApiAccessLevel::class);
    }

    public function getAccessLog($date = null) 
    {
        $date = ($date && strtotime($date)) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        return IpAccessLog::firstOrNew(['ip_id' => $this->id, 'date' => $date]);
    }

    /** BEGIN AccessLogInterface */
    public function incrementDailyHits() 
    {
        $limit = $this->getAccessLimit();

        if($this->isAccessRevoked($limit)) {
            return FALSE;
        }

        $Log = IpAccessLog::firstOrNew(['ip_id' => $this->id, 'date' => date('Y-m-d')]);

        if($Log->limit_reached && $limit > 0) {
            return FALSE;
        }

        $Log->count ++;

        if($limit > 0 && $Log->count >= $limit) {
            $Log->limit_reached = 1;
        }

        $Log->save();
        return TRUE;
    }

    public function getDailyHits($date = null) 
    {
        $date = ($date && strtotime($date)) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

        try {
            $Log = IpAccessLog::where([['ip_id', '=', $this->id], ['date', '=', $date]])->firstOrFail();
        }
        catch (ModelNotFoundException $ex) {
            return 0;
        }

        return intval($Log->count);
    }

    public function isLimitReached($date = null) 
    {
        if($this->getAccessLimit() === 0) {
            return FALSE;
        }

        $date = ($date && strtotime($date)) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

        try {
            $Log = IpAccessLog::where([['ip_id', '=', $this->id], ['date', '=', $date]])->firstOrFail();
        }
        catch (ModelNotFoundException $ex) {
            return FALSE;
        }

        return (bool) $Log->limit_reached;
    }

    public function getAccessLimit() 
    {
        $limit_raw = $this->limit;

        if(ApiAccessManager::isWhitelisted($this->ip_address, $this->domain)) {
            return 0;
        }

        if($this->domain) {
            $current_domain = ApiAccessManager::currentHost();

            if($current_domain && $current_domain == $this->domain) {
                return 0;
            }
        }

        if(!config('bss.public_access')) {
            return -1;
        }

        if($limit_raw === null) {
            $limit_raw = $this->accessLevel->limit;
        }

        if($limit_raw === null) {
            $limit_raw = config('bss.daily_access_limit');
        }

        return $limit_raw;
    }

    public function hasUnlimitedAccess() 
    {
        if($this->isAccessRevoked()) {
            return false;
        }

        return $this->getAccessLimit() === 0;
    }

    public function isAccessRevoked() 
    {
        if($this->access_level_id == ApiAccessLevel::NONE) {
            return true;
        }

        return ($this->getAccessLimit() < 0);
    }    

    // public function isAccessRevoked($limit = null) 
    // {
    //     if($this->access_level_id == ApiAccessLevel::NONE) {
    //         return true;
    //     }

    //     $limit = isset($limit) ? $limit : $this->getAccessLimit()
    //     return ($limit < 0);
    // }
    /** END AccessLogInterface */


    public function delete() {
        IpAccessLog::where('ip_id', $this->id)->delete();
        parent::delete();
    }
}
