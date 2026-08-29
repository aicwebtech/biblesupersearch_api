<?php

namespace App\TextToSpeech;

class MurfAI extends TtsAbstract 
{
    // todo: set the real Murf AI endpoint when this provider is implemented.
    // Was pointing at Narakeet's endpoint, copied from Narakeet.php.
    protected $api_url = null;

    static protected $label = 'Murf AI';
    static protected $is_ai_based = true;

    public function __construct($Bible, $options = [])
    {
        parent::__construct($Bible, $options);
    }

    public function generateAudioHelper($text, $options, $file_handle)
    {
        return false; // todo: implement MurfAI TTS
    }
}
