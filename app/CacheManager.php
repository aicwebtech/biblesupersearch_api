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

        if(!$Cache) {
            $Cache = new Cache();
            $Cache->hash = $this->_generateHash();
            $Cache->hash_long = $this->_generateLongHash($processed);
            $Cache->form_data = $processed;
            $Cache->save();
        }

        return $Cache;
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
