<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class IpAccess extends Model {
    protected $table = 'ip_access';
    protected $fillable = ['ip_address','domain', 'limit'];
    
    static public function findOrCreateByIpOrDomain($ip_address = NULL, $domain = NULL) {
        if($domain) {            
            $domain = str_replace(array('http:','https:'), '', $domain);
            $domain = trim($domain);
            $domain = trim($domain, '/');
        }
        
        if($domain) {
            $IP = static::firstOrNew(['domain' => $domain]);
            $IP->ip_address = $ip_address;
            $IP->save();
        }
        else {
            $IP = static::firstOrCreate(['ip_address' => $ip_address, 'domain' => NULL]);
        }
        
        return $IP;
    }
    
    public function incrementDailyHits() {
        $Log = IpAccessLog::firstOrNew(['ip_id' => $this->id, 'date' => date('Y-m-d')]);
        $limit = $this->getAccessLimit();
        
        if($Log->limit_reached) {
            return FALSE;
        }
        
        $Log->count ++;
        
        if($limit > 0 && $Log->count >= $limit) {
            $Log->limit_reached = 1;
        }
        
        $Log->save();
        return TRUE;
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
        
        $date = (strtotime($date)) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');
        
        try {
            $Log = IpAccessLog::where([['ip_id', '=', $this->id], ['date', '=', $date]])->firstOrFail();
        } 
        catch (ModelNotFoundException $ex) {
            return FALSE;
        }
        
        return ($Log->limit_reached) ? TRUE : FALSE;
    }
    
    public function getAccessLimit() {
        $limit_raw = $this->limit;
        
        if($limit_raw === NULL) {
            $limit_raw = env('DAILY_ACCESS_LIMIT', 1000);
        }
        
        return $limit_raw;
    }
    
    public function delete() {
        IpAccessLog::where('ip_id', $this->id)->delete();
        parent::delete();
    }
}
