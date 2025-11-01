<?php

namespace App;

use App\Models\Bible;
use App\Passage;
use App\Traits\Error;

class AudioManager 
{
    // AudioManager code would go here
    use Error;

    static public $tts_apis = [
        'elevenlabs' => [
            'name' => 'Eleven Labs',
            'base_url' => 'https://api.elevenlabs.io/v1',
            'method' => 'POST',
            'response_type' => 'audio/mpeg',
            'headers' => [
                'xi-api-key' => null, // to be set dynamically
                'Content-Type' => 'application/json',
            ],
        ],
        'narakeet' => [
            'name' => 'Narakeet',
            'base_url' => 'https://api.narakeet.com/text-to-speech/mp3',
            'method' => 'POST',
            'response_type' => 'audio/mpeg',
            'headers' => [
                'Authorization' => null, // to be set dynamically
                'Content-Type' => 'application/json',
            ],
        ],
    ];

    public function getAudioByInput($input, $module = null)
    {
        $Passage = new Passage();
        $Passage->setBook($input['book']);
        $Passage->setChapterVerse($input['chapter_verse']);
        $module = $module ?? $input['bible'] ?? null;

        return $this->getAudio($Passage, $module, $input);
    }

    public function getAudio($Passage, $module, $parameters = [])
    {
        if(is_object($module)) {
            $Bible = $module;
        } else {
            $Bible = Bible::findByModule($input['bible']);
        }
        
        try {
            $verses = $Bible->getAudio([$Passage], []);

            foreach($verses as &$verse) {
                if(!$verse->file_name) {
                    $this->renderAudioTTS($verse, $parameters);
                }
            }
            unset($verse);

            // $text = $Bible->getSearch([$Passage], null, []);

            print_r($verses);
        } catch (\Exception $e) {
            if(config('app.debug')) {
                $this->addTransError('errors.500', [], 4, 500);
            }
            return FALSE;
        }
    }

    protected function renderAudioTTS(&$verse, $parameters = []) 
    {
        $bcv = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;
        
        $filename = 'tts_' . md5($bcv) . '_' . microtime(false) . '.mp3';
        $verse->file_name = $filename;
    }
}