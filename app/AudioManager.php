<?php

namespace App;

use App\Models\Bible;
use App\Passage;
use App\Traits\Error;

class AudioManager 
{
    // AudioManager code would go here
    use Error;

    public $has_all_audio = false;

    static public $tts_apis = [
        // name limited to 100 chars to match DB field!
        // 'elevenlabs' => [
        //     'name'  => 'Eleven Labs',
        //     'class' => \App\TextToSpeech\Elevenlabs::class,
        // ],
        // 'murfai' => [
        //     'name'  => 'Murf AI',
        //     'class' => \App\TextToSpeech\MurfAI::class,
        // ],
        'narakeet' => [
            'name'  => 'Narakeet',
            'class' => \App\TextToSpeech\Narakeet::class,
        ],
        // 'openai' => [
        //     'name'  => 'OpenAI',
        //     'class' => \App\TextToSpeech\OpenAI::class,
        // ],
    ];

    static public function getTtsApisList()
    {
        $list = [];

        foreach(self::$tts_apis as $key => $api) {
            
            $list[] = [
                'key'   => $key,
                'name'  => $api['name'],
                'requires_voice' => ($api['class'])::$requires_voice,
            ];
        }

        return $list;
    }

    public function checkAudioByInput($input, $module = null)
    {
        return $this->getAudioByInput($input, 'generate', $module);
    }

    public function downloadAudioByInput($input, $module = null)
    {
        return $this->getAudioByInput($input, 'get', $module);
    }

    public function getAudioByInput($input, $mode = 'check', $module = null)
    {
        $Passage = new Passage();
        $Passage->setBook($input['book']);
        $Passage->setChapterVerse($input['chapter_verse']);
        $module = $module ?? $input['bible'] ?? null;

        return $this->getAudio($Passage, $module, $input, $mode);
    }

    public function getAudio($Passage, $module, $parameters = [], $mode = null)
    {
        if(is_object($module)) {
            $Bible = $module;
        } else {
            $Bible = Bible::findByModule($input['bible']);
        }
        
        try {
            $verses = $Bible->getAudio([$Passage], []);
            $mp3_str = null;
            $this->has_all_audio = true;

            foreach($verses as &$verse) {
                $verse_has_audio = (bool) $verse->file_name;
                
                if(!$verse->file_name && $mode == 'generate') {
                    $success = $this->renderAudioTTS($Bible, $verse, $parameters);

                    if($success) {
                        $ABV = new \App\Models\AudioBibleVerse();
                        $ABV->module    = $Bible->module;
                        $ABV->book      = $verse->book;
                        $ABV->chapter   = $verse->chapter;
                        $ABV->verse     = $verse->verse;
                        $ABV->file_name = $verse->file_name;
                        $ABV->save();
                        $verse_has_audio = true;
                    }
                }

                if($mode == 'get') {
                    if(!isset($verse->file_name)) {
                        $this->addTransError('errors.audio_file_missing', ['bcv' => $verse->book . ' ' . $verse->chapter . ':' . $verse->verse]);
                        continue;
                    }
                    
                    $file_path = \App\TextToSpeech\TtsAbstract::getAudioFilePathStatic($Bible->module) . '/' . $verse->file_name;

                    if(file_exists($file_path)) {
                        $mp3_str .= file_get_contents($file_path);
                    } else {
                        $this->addTransError('errors.audio_file_missing', ['bcv' => $verse->book . ' ' . $verse->chapter . ':' . $verse->verse]);
                    }
                }

                $this->has_all_audio = $this->has_all_audio && $verse_has_audio;
            }
            unset($verse);

            if($mode == 'get') {
                if($this->hasErrors()) {
                    return FALSE;
                } else {
                    header('Content-Description: File Transfer');
                    header('Content-Type: audio/mpeg');
                    header('Content-Disposition: inline');
                    header('Content-Transfer-Encoding: binary');
                    header('Access-Control-Allow-Origin: *');
                    header('Expires: 0');
                    
                    // :todo - determine proper caching headers for debug and production
                    // :todo - figure out how to send duration of audio

                    $duration = null;

                    // Fallback: estimate from filesize using assumed bitrate (128 kbps)
                    if ($duration === null) {
                        $size = strlen($mp3_str);

                        if ($size && $size > 0) {
                            $assumed_bitrate = 163840; // bits per second
                            $duration = ($size * 8) / $assumed_bitrate;
                        }
                    }

                    // calculated duratoin is correct for narakeet
                    // headers appear to be non-standard ... 

                    if ($duration !== null) {
                        header('X-Content-Duration: ' . number_format($duration, 3));
                        header('X-Audio-Duration: ' . number_format($duration, 3));
                        header('Content-Duration: ' . number_format($duration, 3));
                    }

                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Cache-Control: private', false);
                    header('Pragma: public');
                    header('Content-Length: ' . strlen($mp3_str));
                    
                    echo($mp3_str);

                    exit;
                }
            }

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

        $tts = self::$tts_apis[ config('audio.tts_api') ] ?? null;

        if(!$tts) {
            return $this->addError('TTS API NOT supported: ' . config('audio.tts_api'));
        }

        $TTS = new $tts['class']($Bible, $parameters);

        $success = $TTS->generateAudio($verse->text, $parameters, $filename);

        if(!$success) {
            return $this->addTransError('errors.audio_tts_failed', ['bcv' => $bcv]);
        } else {
            $verse->file_name = $filename;
        }

        return true;
    }
}