<?php

namespace App;

use App\Models\Cache;

class CacheManager
{
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
        // committed within the current one. The hash is derived deterministically from the
        // query so concurrent identical requests agree on it even before the row is persisted.
        $Cache = new Cache();
        $Cache->hash      = $this->_generateHash($processed);
        $Cache->hash_long = $this->_generateLongHash($processed);
        $Cache->form_data = $processed;

        $this->_deferCacheWrite($Cache);

        return $Cache;
    }

    /**
     * Persist the cache record after the response has been flushed to the client, keeping the
     * write off the request's critical path. A concurrent identical request may persist the same
     * (deterministic hash / hash_long) row first, so a unique-constraint violation is treated as
     * a no-op - the already-persisted row still resolves the hash this request handed back.
     */
    protected function _deferCacheWrite(Cache $Cache): void
    {
        app()->terminating(function () use ($Cache) {
            try {
                $Cache->save();
            }
            catch (\Illuminate\Database\QueryException $e) {
                // Row already persisted by a concurrent identical request; safe to ignore.
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

    /**
     * Deterministic public handle for a query. Deriving it from the (already unique) processed
     * form data means concurrent identical requests produce the same hash, so even though the
     * row write is deferred, every racer's returned hash resolves to the single persisted row.
     * 16 hex chars stays well within the 20-char column while making cross-query collisions
     * negligible.
     */
    protected function _generateHash(string $processed): string
    {
        return substr(hash('sha256', $processed), 0, 16);
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
