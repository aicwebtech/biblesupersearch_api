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
        if($ip_address === true) {
            $default_host = (array_key_exists('HTTP_REFERER', $_SERVER)) ? $_SERVER['HTTP_REFERER'] : 'localhost';
            $host = $host ?: $default_host;
            $ip_address = (array_key_exists('REMOTE_ADDR', $_SERVER))  ? $_SERVER['REMOTE_ADDR']  : '127.0.0.1';
        }

        $domain = ApiAccessManager::parseDomain($host);

        if($domain) {
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
            $current_domain = '';

            if(array_key_exists('HTTP_HOST', $_SERVER)) {
                $current_domain = $_SERVER['HTTP_HOST'];
            }
            elseif(array_key_exists('SERVER_NAME', $_SERVER)) {
                $current_domain = $_SERVER['SERVER_NAME'];
            }
            
            $current_domain = ApiAccessManager::parseDomain($current_domain);

            if($current_domain == $this->domain) {
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
