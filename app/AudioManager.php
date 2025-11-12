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
            'name'  => 'Eleven Labs',
            'class' => \App\TextToSpeech\Elevenlabs::class,
        ],
        'murfai' => [
            'name'  => 'Murf AI',
            'class' => \App\TextToSpeech\MurfAI::class,
        ],
        'narakeet' => [
            'name'  => 'Narakeet',
            'class' => \App\TextToSpeech\Narakeet::class,
        ],
        'openai' => [
            'name'  => 'OpenAI',
            'class' => \App\TextToSpeech\OpenAI::class,
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

            //print_r($verses); die();
            // return $verses;

            foreach($verses as &$verse) {
                if(!$verse->file_name) {
                    $success = $this->renderAudioTTS($Bible, $verse, $parameters);

                    if($success) {
                        $ABV = new \App\Models\AudioBibleVerse();
                        $ABV->module    = $Bible->module;
                        $ABV->book      = $verse->book;
                        $ABV->chapter   = $verse->chapter;
                        $ABV->verse     = $verse->verse;
                        $ABV->file_name = $verse->file_name;
                        $ABV->save();
                    }
                }
            }
            unset($verse);

            return $verses;
        } catch (\Exception $e) {
            if(config('app.debug')) {
                $this->addTransError('errors.500', [], 4, 500);
            } else {
                throw $e;
            }
            return FALSE;
        }
    }

    protected function renderAudioTTS($Bible, &$verse, $parameters = []) 
    {
        $bcv = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;

        // file name is str padded book, chapter, verse
        $filename = str_pad($verse->book, 2, '0', STR_PAD_LEFT) . '_' .
                    str_pad($verse->chapter, 3, '0', STR_PAD_LEFT) . '_' .
                    str_pad($verse->verse, 3, '0', STR_PAD_LEFT) . '.mp3';

        $verse->file_name = $filename;

        $TTS = new \App\TextToSpeech\Narakeet($Bible, $parameters);

        $success = $TTS->generateAudio($verse->text, $parameters, $filename);

        if(!$success) {
            $this->addTransError('errors.audio_tts_failed', ['bcv' => $bcv]);
            return FALSE;
        } else {
            $verse->file_name = $filename;
        }

        return true;
    }
}