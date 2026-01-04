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
        // return false;
        
        $apikey = $this->getApiKey();
        $voice = $this->getVoice();

        if(!$voice) {
            return $this->addTransError('errors.audio.no_tts_voice', ['api' => self::$label, 'language' => $this->Bible->lang_short]);
        }

        // var_dump($voice);
        // var_dump($filename);
        // die($file_path);
        // return false;

        $data = [
            'model' => 'gpt-4o-mini-tts',
            'voice' => $voice,
            'text' => $text,
            'instructions' => 'Text is in the language of ' . $this->Bible->lang_short,
        ];

        $url = "https://api.openai.com/v1/audio/speech";

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $apikey",
                'Content-Type: application/json',
            ],
            CURLOPT_FILE => $file_handle,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, $options);
        $result = curl_exec($curl);
        $curl_error = curl_error($curl);
        $curl_info = curl_getinfo($curl);
        curl_close($curl);

        print_r($result);
        print_r($curl_info);
        print_r($curl_error);

        return true;
    }
}
