<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use App\Interfaces\AccessLogInterface;
use App\Models\IpAccess;

class ApiKey extends Model implements AccessLogInterface
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'access_level_id' => null,
    ];

    public function __construct()
    {
        parent::__construct();

        $this->attributes['access_level_id'] = ApiAccessLevel::BASIC;
    }

    public function delete()
    {
        $this->access_level_id = ApiAccessLevel::NONE;
        $this->save();
        parent::delete();
    }

    static public function findByKey($key, $fail = false) 
    {
        if($fail) {
            return static::where('key', $key)->firstOrFail();
        } else {
            return static::where('key', $key)->first();
        }
    }

    static public function generateKeyHash()
    {
        $hash = static::generateHashHelper();
        $Key  = static::withTrashed()->where('key', $hash)->first();

        while($Key) {
            $hash = static::generateHashHelper();
            $Key  = static::withTrashed()->where('key', $hash)->first();
        }

        return $hash;
    }

    private static function generateHashHelper() 
    {
        $hash_size = 24;
        $hash = 'TrU';

        $hash .= Str::Random(10);

        for($i = 1; $i <= $hash_size; $i ++) {
            $num  = random_int(0, 35);
            $char = ($num < 10) ? $num : chr($num + 87);
            $hash .= $char;
        }

        return $hash;
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

        $Log = ApiKeyAccessLog::firstOrNew(['key_id' => $this->id, 'date' => date('Y-m-d')]);
        $limit = $this->getAccessLimit();

        if($Log->limit_reached && $limit > 0) {
            return FALSE;
        }

        $Log->count ++;

        if($limit > 0 && $Log->count >= $limit) {
            $Log->limit_reached = 1;
        }

        $Log->save();

        // For tracking purposes, we also log the hits against the IP with the key, however, this count is not used to determine limit overage, ect.
        $IP = IpAccess::findOrCreateByIpOrDomain(true);
        $IpKeyCount = ApiIpKeyCount::firstOrNew(['key_id' => $this->id, 'ip_id' => $IP->id, 'date' => date('Y-m-d')]);
        $IpKeyCount->count ++;
        $IpKeyCount->save();

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
        if($this->isAccessRevoked()) {
            return false;
        }

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
