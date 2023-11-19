<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Interfaces\AccessLogInterface;
use App\Models\IpAccess;

class ApiKey extends Model implements AccessLogInterface
{
    use HasFactory;

    protected $attributes = [
        'access_level_id' => null,
    ];

    public function __construct()
    {
        parent::__construct();

        $this->attributes['access_level_id'] = ApiAccessLevel::BASIC;
    }

    static public function findByKey($key, $fail) 
    {
        if($fail) {
            return static::where('key', $key)->firstOrFail();
        } else {
            return static::where('key', $key)->first();
        }
    }

    public function accessLevel()
    {
        return $this->belongsTo(ApiAccessLevel::class);
    }

    /** BEGIN AccessLogInterface */
    public function incrementDailyHits() 
    {
        if($this->isAccessRevoked()) {
            return FALSE;
        }

        $IP = IpAccess::findOrCreateByIpOrDomain(true);
        $Log = ApiKeyAccessLog::firstOrNew(['key_id' => $this->id, 'date' => date('Y-m-d'), 'ip_id' => $IP->id]);
        $limit = $this->getAccessLimit();

        if($Log->limit_reached && $limit > 0) {
            return FALSE;
        }

        $Log->count ++;

        if($limit > 0 && $Log->count >= $limit) {
            $Log->limit_reached = 1;
        }

        $Log->save();

        // For tracking purposes, we log the hits against the IP, however, the IP is not used to determine limits, ect.
        $IP->incrementDailyHits(); // for tracking pu
        return TRUE;
    }

    public function getDailyHits($date = null) 
    {
        $date = (strtotime($date)) ? date('Y-m-d', strtotime($date)) : date('Y-m-d');

        try {
            $Log = ApiKeyAccessLog::where([['key_id', '=', $this->id], ['date', '=', $date]])->firstOrFail();
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
            $Log = ApiKeyAccessLog::where([['key_id', '=', $this->id], ['date', '=', $date]])->firstOrFail();
        }
        catch (ModelNotFoundException $ex) {
            return FALSE;
        }

        return (bool) $Log->limit_reached;
    }

    public function getAccessLimit() 
    {
        $limit_raw = $this->limit;

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
        return $this->getAccessLimit() === 0;
    }

    public function isAccessRevoked() {
        if($this->access_level_id == ApiAccessLevel::NONE) {
            return true;
        }

        return ($this->getAccessLimit() < 0);
    }
    /** END AccessLogInterface */
}
