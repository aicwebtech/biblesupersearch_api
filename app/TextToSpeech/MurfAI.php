<?php

namespace App\TextToSpeech;

class MurfAI extends TtsAbstract 
{
    protected $api_url = 'https://api.narakeet.com/text-to-speech/mp3';

    public function __construct($Bible, $options = [])
    {
        parent::__construct($Bible, $options);
    }

    public function generateAudioHelper($text, $options, $file_handle)
    {
        return false; // todo: implement MurfAI TTS
    }
}