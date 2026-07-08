<?php

namespace App;

use App\Models\Cache;

class CacheManager 
{
    protected $hash_size = 10;

    public function createCache($form_data, $parsing = [])
    {
        $processed = $this->processFormData($form_data, $parsing);
        // Attempt to find / reuse an existing cache - is this a good idea?
        $Cache = $this->_getCacheByProcessedFormData($processed);

        if($Cache) {
            return $Cache;
        }

        // Fresh query: populate the record now so the caller has its hash immediately, but
        // defer the synchronous INSERT (~50ms on live) until after the response is sent. The
        // cache row is only ever read back on a *later* request, so it never needs to be
        // committed within the current one.
        $Cache = new Cache();
        $Cache->hash      = $this->_generateHash();
        $Cache->hash_long = $this->_generateLongHash($processed);
        $Cache->form_data = $processed;

        $this->_deferCacheWrite($Cache);

        return $Cache;
    }

    /**
     * Persist the cache record after the response has been flushed to the client, keeping the
     * write off the request's critical path. A concurrent identical request may insert the same
     * row first, so a unique-constraint violation is treated as a no-op.
     */
    protected function _deferCacheWrite(Cache $Cache): void
    {
        app()->terminating(function () use ($Cache) {
            try {
                $Cache->save();
            } catch (\Illuminate\Database\QueryException $e) {
                // Ignore duplicate-key errors from a concurrent identical request; rethrow anything else.
                if (!isset($e->errorInfo[1]) || (int) $e->errorInfo[1] !== 1062) {
                    throw $e;
                }
            }
        });
    }

    /**
     * Need a cron job to run this
     */
    public function cleanUpCache() 
    {
        $Caches = Cache::where('preserve', 0)->whereRaw('created_at + INTERVAL 1 MONTH < NOW()')->delete();
    }

    public function getCacheByHash($hash) 
    {
        return Cache::where('hash', $hash)->first();
    }    

    public function getCacheByFormData($form_data) 
    {
        $processed = $this->processFormData($form_data);
        return $this->_getCacheByProcessedFormData($processed);
    }

    protected function _getCacheByProcessedFormData($processed) 
    {
        $hash_long = $this->_generateLongHash($processed);
        return Cache::where('hash_long', $hash_long)->first();
    }

    protected function _generateHash() 
    {
        $hash = $this->_generateHashHelper();
        $Cache = Cache::where('hash', $hash)->first();

        while($Cache) {
            $hash = $this->_generateHashHelper();
            $Cache = Cache::where('hash', $hash)->first();
        }

        return $hash;
    }

    private function _generateHashHelper() 
    {
        $hash = '';

        for($i = 1; $i <= $this->hash_size; $i ++) {
            $num  = rand(0, 35);
            $char = ($num < 10) ? $num : chr($num + 87);
            $hash .= $char;
        }

        return $hash;
    }

    protected function _generateLongHash($processed) 
    {
        return md5($processed);
    }

    protected function processFormData($form_data, $parsing = []) 
    {
        $processed = array();
        $exclude   = ['page','page_all'];

        if(!empty($parsing) && is_array($parsing)) {
            foreach($parsing as $key => $info) {
                if(array_key_exists($key, $form_data) && !in_array($key, $exclude)) {
                    $processed[$key] = $form_data[$key];
                }
            }
        } else {
            $processed = $form_data;
        }

        ksort($processed);
        return json_encode($processed);
    }
}
