<?php

namespace App\TextToSpeech;

class OpenAI extends TtsAbstract 
{
    static protected $label = 'OpenAI';

    public function __construct($Bible, $options = [])
    {
        parent::__construct($Bible, $options);
    }

    public function generateAudioHelper($text, $options, $file_handle)
    {
        $s = $this->getSettings();

        if(!$s) {
            return false;
        }

        $data = [
            'model' => 'gpt-4o-mini-tts',
            'voice' => $s['voice'],
            'input' => $text,
            'instructions' => 'Text is in the language of ' . $this->Bible->lang_short,
        ];

        if($s['speed'] && $s['speed'] != 1.0) {
            $data['speed'] = (float) $s['speed'];
        }

        $url = "https://api.openai.com/v1/audio/speech";

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$s['api_key']}",
                'Content-Type: application/json',
            ],
            CURLOPT_FILE => $file_handle,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $result = curl_exec($curl);
        $curl_info = curl_getinfo($curl);
        curl_close($curl);

        if($curl_info['http_code'] != 200) {
            return false;
        }

        return true;
    }
}
