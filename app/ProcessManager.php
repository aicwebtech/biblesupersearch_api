<?php

namespace aicwebtech\BibleSuperSearch;

use aicwebtech\BibleSuperSearch\Models\Process;

class ProcessManager {
    protected $hash_size = 10;

    public function createProcess($api_action, $form_data, $parsing = array()) {
        $processed = $this->processFormData($form_data, $parsing);
        // Attempt to find / reuse an existing Process - is this a good idea?
        $Process = $this->_getProcessByProcessedFormData($processed);

        if(!$Process) {
            $Process = new Process();
            $Process->hash = $this->_generateHash();
            $Process->form_data = $processed;
            $Process->save();
        }

        return $Process;
    }

    public function appendProcessUser($api_action, $form_data, $parsing = array()) {

    }

    /**
     * Need a cron job to run this
     */
    public function cleanUpProcess() {
        $Processes = Process::where('preserve', 0)->whereRaw('created_at + INTERVAL 1 MONTH < NOW()')->delete();
    }

    public function getProcessByFormData($form_data) {
        $processed = $this->processFormData($form_data);
        return $this->_getProcessByProcessedFormData($processed);
    }

    protected function _getProcessByProcessedFormData($processed) {
        return Process::where('form_data', $processed)->first();
    }

    protected function _generateHash() {
        $hash = $this->_generateHashHelper();
        $Process = Process::where('hash', $hash)->first();

        while($Process) {
            $hash = $this->_generateHashHelper();
            $Process = Process::where('hash', $hash)->first();
        }

        return $hash;
    }

    private function _generateHashHelper() {
        $hash = '';

        for($i = 1; $i <= $this->hash_size; $i ++) {
            $num  = rand(0, 35);
            $char = ($num < 10) ? $num : chr($num + 87);
            $hash .= $char;
        }

        return $hash;
    }

    protected function processFormData($form_data, $parsing = array()) {
        $processed = array();
        $exclude   = ['page','page_all'];

        if(!empty($parsing) && is_array($parsing)) {
            foreach($parsing as $key => $info) {
                if(array_key_exists($key, $form_data) && !in_array($key, $exclude)) {
                    $processed[$key] = $form_data[$key];
                }
            }
        }
        else {
            $processed = $form_data;
        }

        ksort($processed);
        return json_encode($processed);
    }
}
