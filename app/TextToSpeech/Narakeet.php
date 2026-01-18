<?php

namespace App\TextToSpeech;

class Narakeet extends TtsAbstract 
{
    protected $api_url = 'https://api.narakeet.com/text-to-speech/mp3';

    static protected $label = 'Narakeet';
    static protected $is_ai_based = true;
    static protected $requires_voice = true;

    public function __construct($Bible, $options = [])
    {
        parent::__construct($Bible, $options);
    }

    public function generateAudioHelper($text, $options, $file_handle)
    {
        $s = $this->getSettings();

        if(!$s['voice']) {
            return $this->addTransError('errors.audio.no_tts_voice', ['api' => self::$label, 'language' => $this->Bible->lang_short]);
        }

        $url = "https://api.narakeet.com/text-to-speech/mp3?voice={$s['voice']}" ;

        if(isset($s['speed']) && $s['speed'] != 1.0) {
            $speed = (float) $s['speed'];
            $url .= "&voice-speed=$speed";
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $text,
            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream',
                'Content-Type: text/plain',
                "x-api-key: {$s['api_key']}",
            ],
            CURLOPT_FILE => $file_handle,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $success = curl_exec($curl);
        $curl_info = curl_getinfo($curl);
        curl_close($curl);

        if($curl_info['http_code'] != 200) {
            return false;
        }

        return true;
    }
}