<?php

namespace App\TextToSpeech;

class Narakeet extends TtsAbstract 
{
    protected $api_url = 'https://api.narakeet.com/text-to-speech/mp3';

    static protected $label = 'Narakeet';

    public function __construct($Bible, $options = [])
    {
        parent::__construct($Bible, $options);
    }

    public function generateAudioHelper($text, $options, $file_handle)
    {
        $apikey = config('audio.tts_api_key');
        $voice = static::getVoiceByLanguage($this->Bible->lang_short);

        // var_dump($voice);
        // var_dump($filename);
        // die($file_path);
        // return false;

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
        // $curl_error = curl_error($curl);
        // $curl_info = curl_getinfo($curl);
        curl_close($curl);

        // print_r($curl_info);
        // print_r($curl_error);

        return true;
    }


}