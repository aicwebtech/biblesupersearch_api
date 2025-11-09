<?php

namespace App\TextToSpeech;

class Narakeet extends TtsAbstract 
{
    protected $api_url = 'https://api.narakeet.com/text-to-speech/mp3';

    protected $file_extension = 'mp3';

    public function __construct($Bible, $options = [])
    {
        parent::__construct($Bible, $options);
    }

    public function generateAudio($text, $options = [], $filename = null)
    {
        $apikey = config('audio.tts_api_key');
        $voice = $this->_getVoiceByLanguage($this->Bible->lang_short);

        $path = $this->getAudioFilePath(true);

        if($filename) {
            $file_path = $path . '/' . $filename;
        } else {
            $file_path = $path . '/narakeet_' . md5($text . microtime(false)) . '.mp3';
        }

        $file_handle = fopen($file_path, 'w');
        $text = $this->_formatText($text);

        var_dump($voice);
        // var_dump($filename);
        // die($file_path);
        return false;

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
        fclose($file_handle);

        // print_r($curl_info);
        // print_r($curl_error);

        return true;
    }

    protected function _getVoiceByLanguage($language_short)
    {
        
        // :todo let user select voice by language?
        // :todo let user select male vs female voice?

        $voice = config('lang.' . $language_short . '.text_to_speech.narakeet.voices.default');

        if($voice) {
            return $voice;
        }

        $voice_default = config('text_to_speech.narakeet.voices.default');

        return $voice_default;

        $map = [
            'en' => 'brian',
            'es' => 'carmen',
            'fr' => 'celine',
            'lv' => 'kristaps',
            'de' => 'anna',
            'it' => 'carlo',
            'pt' => 'joana',
            'ru' => 'nikolai',
            'zh' => 'meilin',
        ];

        if(isset($map[$language_short])) {
            return $map[$language_short];
        }

        return 'brian';
    }
}