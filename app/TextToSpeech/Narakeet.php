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
        $apikey = $this->getApiKey();
        $voice = $this->getVoice();

        if(!$voice) {
            return $this->addTransError('errors.audio.no_tts_voice', ['api' => self::$label, 'language' => $this->Bible->lang_short]);
        }

        $url = "https://api.narakeet.com/text-to-speech/mp3?voice=$voice";

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $text,
            CURLOPT_HTTPHEADER => [
                'Accept: application/octet-stream',
                'Content-Type: text/plain',
                "x-api-key: $apikey",
            ],
            CURLOPT_FILE => $file_handle,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        curl_exec($curl);
        curl_close($curl);

        return true;
    }
}