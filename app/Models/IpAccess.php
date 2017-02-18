<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpAccess extends Model {
    protected $table = 'ip_access';
    
    static public function findOrCreateByIpOrDomain($ip_address = NULL, $domain = NULL) {
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
    
    public function getAccessLimit() {
        $limit_raw = $this->limit;
        
        if($limit_raw === NULL) {
            $limit_raw = env('DAILY_ACCESS_LIMIT', 1000);
        }
        
        return $limit_raw;
    }
}
