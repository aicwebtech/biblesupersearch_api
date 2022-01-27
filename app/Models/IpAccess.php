<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class IpAccess extends Model {
    protected $table = 'ip_access';
    protected $fillable = ['ip_address','domain', 'limit'];

    static public function findOrCreateByIpOrDomain($ip_address = NULL, $host = NULL) {
        $domain = static::parseDomain($host);

        if($domain) {
            $IP = static::firstOrNew(['domain' => $domain]);
            $IP->ip_address = $ip_address;
            $IP->limit = ($ip_address == '127.0.0.1' || $ip_address == '::1') ? 0 : NULL;
            $IP->save();
        }
        else {
            $IP = static::firstOrCreate(['ip_address' => $ip_address, 'domain' => NULL]);
        }

        return $IP;
    }

    static public function parseDomain($host) {
        if(empty($host)) {
            return NULL;
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
            return NULL;
        }

        return $domain;
    }

    public function incrementDailyHits() {
        if($this->isAccessRevoked()) {
            return FALSE;
        }

        $Log = IpAccessLog::firstOrNew(['ip_id' => $this->id, 'date' => date('Y-m-d')]);
        $limit = $this->getAccessLimit();

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

    public function getAccessLog() {
        return IpAccessLog::firstOrNew(['ip_id' => $this->id, 'date' => date('Y-m-d')]);
    }

    public function getDailyHits($date = NULL) {
        $date = (strtotime($date)) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

        try {
            $Log = IpAccessLog::where([['ip_id', '=', $this->id], ['date', '=', $date]])->firstOrFail();
        }
        catch (ModelNotFoundException $ex) {
            return 0;
        }

        return intval($Log->count);
    }

    public function isLimitReached($date = NULL) {
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

    public function getAccessLimit() {
        $limit_raw = $this->limit;

        if($this->domain) {
            $current_domain = '';

            if(array_key_exists('HTTP_HOST', $_SERVER)) {
                $current_domain = $_SERVER['HTTP_HOST'];
            }
            elseif(array_key_exists('SERVER_NAME', $_SERVER)) {
                $current_domain = $_SERVER['SERVER_NAME'];
            }
            
            $current_domain = static::parseDomain($current_domain);

            if($current_domain == $this->domain) {
                return 0;
            }
        }

        if($limit_raw === NULL) {
            $limit_raw = config('bss.daily_access_limit');
        }

        return $limit_raw;
    }

    public function isAccessRevoked() {
        return ($this->getAccessLimit() < 0);
    }

    public function delete() {
        IpAccessLog::where('ip_id', $this->id)->delete();
        parent::delete();
    }
}
