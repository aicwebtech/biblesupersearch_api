<?php

namespace App;

use App\Models\Bible;
use App\Passage;
use App\Traits\Error;
use App\Interfaces\ErrorInterface;
use App\TextToSpeech\Mp3;
use App\TextToSpeech\Ffmpeg;
use App\TextToSpeech\TtsAbstract;
use App\Models\AudioBibleVerse;

class AudioManager implements ErrorInterface
{
    // AudioManager code would go here
    use Error;

    public $has_all_audio = false;

    static public $tts_apis = [
        // Name limited to 100 chars to match DB field!
        // 'elevenlabs' => \App\TextToSpeech\Elevenlabs::class,
        // 'murfai'   => \App\TextToSpeech\MurfAI::class,
        'narakeet' => \App\TextToSpeech\Narakeet::class,
        // 'openai'   => \App\TextToSpeech\OpenAI::class,
    ];

    static public $filename_matches = [
        'auto' => [
            // this is just a placeholder for auto-detect
            'label' => 'Auto Detect',
            'pattern' => '',
            'type' => 'auto',
            'auto' => false,
        ],
        'verse' => [
            'label' => 'Verse (most)',
            // grabs first two digit number as book, next two or three digit as chapter, next two or three as verse
            'pattern' => '/(\d{2})[^\d]+(\d{2,3})[^\d]+(\d{2,3})[^\d]*\.mp3$/',
            'type' => 'verse',
            'auto' => true,
        ],
        'chapter' => [
            'label' => 'Chapter (most)',
            // grabs first two digit number as book, next two or three digit as chapter
            'pattern' => '/(\d{2})[^\d]+(\d{2,3})[^\d]*\.mp3$/',
            'type' => 'chapter',
            'auto' => true,
            'examples' => ['01-01-GEN.mp3', '55_II_Timothy_03.mp3']
        ],
        'chapter_custom_lv' => [
            'label' => 'Chapter Custom - Latvian',
            'pattern' => '/(\d{2})[^\d]+(\d{2,3})[^\d]*\.mp3$/',
            //'pattern' => '/\d+\-(\d{2,3})[^\d]*\.mp3$/',
            'type' => 'chapter',
            'auto' => false,
            'examples' => ['C02-01-GEN-01.mp3', 'C02-19-PSA-01.mp3, C02-19-PSA-100.mp3'],
        ],
    ];

    static public function getTtsApisList()
    {
        $list = [];

        foreach(self::$tts_apis as $key => $class) {
            $meta = ($class)::getMeta();
            $meta['key'] = $key;
            $list[] = $meta;
        }

        return $list;
    }

    static public function getFilenameMatchesList()
    {
        $list = [];

        foreach(self::$filename_matches as $key => $match) {
            $item = $match;
            $item['key'] = $key;
            $item['pattern'] = trim($item['pattern'], '/');
            $list[] = $item;
        }

        return $list;
    }

    static public function getTtsApiClasses()
    {
        return static::$tts_apis;
    }

    static public function audioEnabled($Bible = null)
    {
        $audio_enabled = (bool)config('audio.enable', false);

        if(!$audio_enabled) {
            return false;
        }

        if($Bible) {
            if(!$Bible->audio_enable) {
                return false;
            }
        }

        return true;
    }

    static public function ttsEnabled($Bible = null)
    {
        $tts_enabled = (bool)config('audio.tts_api_enable', false);

        if(!$tts_enabled) {
            return false;
        }

        if($Bible) {
            if(!$Bible->audio_enable || !$Bible->tts_enable) {
                return false;
            }
        }

        return true;
    }

    static public function isTtsAI($Bible = null)
    {
        if($Bible && (!$Bible->audio_enable || !$Bible->tts_enable)) {
            return false;
        }

        $tts_class = self::$tts_apis[ config('audio.tts_api', 'narakeet') ] ?? null;

        if(!$tts_class) {
            return false;
        }

        return $tts_class::getMeta()['is_ai_based'] ?? false;
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

        if(!$Bible->audio_enable) {
            return $this->addTransError('errors.audio.bible_no_audio', ['module' => $Bible->module], 4);
        }
        
        try {
            $verses = $Bible->getAudio([$Passage], []);

            $compat_mode = !Ffmpeg::canUse();
            $mp3_str = null;
            $mp3_size = 0;
            $single_verse = (count($verses) == 1);
            $this->has_all_audio = count($verses) > 0;
            $file_paths = [];

            if($mode == 'get' && $compat_mode) {
                // create tmp file for mp3 concatenation
                $mp3_tmp = tmpfile();
            }

            foreach($verses as &$verse) {
                $verse_has_audio = (bool) $verse->file_name;
                
                if(!$verse->file_name && $mode == 'generate') {
                    if($this->checkCanRenderTts($Bible) !== true) {
                        continue;
                    }
                    
                    $success = $this->renderAudioTTS($Bible, $verse, $parameters);

                    if($success) {
                        $ABV = new \App\Models\AudioBibleVerse();
                        $ABV->module    = $Bible->module;
                        $ABV->book      = $verse->book;
                        $ABV->chapter   = $verse->chapter;
                        $ABV->verse     = $verse->verse;
                        $ABV->file_name = $verse->file_name;
                        $ABV->source    = config('audio.tts_api', 'narakeet');
                        $ABV->voice     = $success['voice'] ?? null;
                        $ABV->save();
                        $verse_has_audio = true;
                    }
                }

                if($mode == 'get') {
                    if(!isset($verse->file_name)) {
                        $this->addTransError('errors.audio_file_missing', ['bcv' => $verse->book . ' ' . $verse->chapter . ':' . $verse->verse]);
                        continue;
                    }
                    
                    $file_path = TtsAbstract::getAudioFilePathStatic($Bible->module) . '/' . $verse->file_name;

                    if(file_exists($file_path)) {
                        $file_paths[] = realpath($file_path);
                        
                        //$mp3_str .= file_get_contents($file_path);
                        // write to tmp file
                        // $fp = fopen($file_path, 'rb');

                        // $MP3 = MpegAudio::fromFile($file_path);
                        // $frameCount = $MP3->getStart();

                        $MP3 = new Mp3($file_path);                        
                        $MP3->stripTags();

                        $str_sans_tags = $MP3->getStr();

                        if ($compat_mode) {
                            fwrite($mp3_tmp, $MP3->getStr());
                        }
                        
                        $mp3_size += strlen($MP3->getStr());

                        // if($fp) {
                        //     stream_copy_to_stream($fp, $mp3_tmp);
                        //     fclose($fp);
                        //     $verse_has_audio = true;
                        // }
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
                    // :todo CLEAN THIS UP!!!!
                    //print_r($file_paths); die('PATHETIC');

                    header('Content-Description: File Transfer');
                    header('Content-Type: audio/mpeg');
                    header('Content-Disposition: inline');
                    header('Content-Transfer-Encoding: binary');
                    header('Access-Control-Allow-Origin: *');
                    header('Expires: 0');
                    // header('Transfer-Encoding: chunked'); // not needed and causes issues

                    // :todo - determine proper caching headers for debug and production
                    // :todo - figure out how to send duration of audio

                    $duration = null;

                    // Fallback: estimate from filesize using assumed bitrate (160 kbps)
                    if ($duration === null) {
                        // $size = strlen($mp3_str);

                        if ($mp3_size && $mp3_size > 0) {
                            $assumed_bitrate = 163840; // bits per second
                            $duration = ($mp3_size * 8) / $assumed_bitrate;
                        }
                    }

                    // calculated duration is correct for narakeet
                    // headers appear to be non-standard ... 
                    // if ($duration !== null) {
                    //     header('X-Content-Duration: ' . number_format($duration, 3));
                    //     header('X-Audio-Duration: ' . number_format($duration, 3));
                    //     header('Content-Duration: ' . number_format($duration, 3));
                    // }

                    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                    header('Cache-Control: private', false);
                    header('Pragma: public');
                    header('Content-Length: ' . $mp3_size);
                    // header('Accept-Ranges: bytes');
                    // http_response_code(206); // Partial Content

                    if (!$compat_mode) {
                        $tmp_file = tempnam(sys_get_temp_dir(), 'audiobib_');
                        rename($tmp_file, $tmp_file . '.mp3');
                        $tmp_file .= '.mp3';

                        if(!Ffmpeg::quickMerge($file_paths, $tmp_file)) {
                            return $this->addTransError('errors.audio.merge_failed', ['errors'=> implode("\n", Ffmpeg::$useErrors)]);
                        }
                    }
                    
                    if (false && count($verses) == 1) {
                        $verse = $verses[0];
                        // header('Content-Disposition: inline; filename="' . $verse->file_name . '"');
                        $file_path = \App\TextToSpeech\TtsAbstract::getAudioFilePathStatic($Bible->module) . '/' . $verse->file_name;
                        readfile($file_path);
                    } else {
                        // header('Content-Disposition: inline; filename="audio.mp3"');

                        if($compat_mode) {
                            rewind($mp3_tmp);
                            fpassthru($mp3_tmp);
                        } else {
                            readfile($tmp_file);
                            unlink($tmp_file);
                        }
                    }

                    if ($mp3_tmp) {
                        fclose($mp3_tmp);
                    }

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

    protected function checkCanRenderTts($Bible) 
    {
        $tts_enabled = (bool)config('audio.tts_api_enable', false);

        if(!$tts_enabled) {
            return $this->addTransError('errors.audio.no_tts');
        }

        if(!$Bible->audio_enable) {
            return $this->addTransError('errors.audio.bible_no_audio', ['module' => $Bible->module]);
        }

        if(!$Bible->tts_enable) {
            return $this->addTransError('errors.audio.no_tts_bible', ['module' => $Bible->module]);
        }

        if($Bible->audio_structure == 'chapters') {
            return $this->addTransError('errors.audio.unsupported_tts_structure');
        }

        return true;
    }
    
    protected function renderAudioTTS($Bible, &$verse, $parameters = []) 
    {
        $tts_enabled = (bool)config('audio.tts_api_enable', false);

        if(!$tts_enabled) {
            return $this->addTransError('errors.audio.no_tts');
        }

        if(!$Bible->audio_enable) {
            return $this->addTransError('errors.audio.bible_no_audio', ['module' => $Bible->module]);
        }

        if(!$Bible->tts_enable) {
            return $this->addTransError('errors.audio.no_tts_bible', ['module' => $Bible->module]);
        }

        if($Bible->audio_structure == 'chapters') {
            return $this->addTransError('errors.audio.unsupported_tts_structure');
        }
        
        $bcv = $verse->book . ' ' . $verse->chapter . ':' . $verse->verse;

        // file name is str padded book, chapter, verse
        $filename = str_pad($verse->book, 2, '0', STR_PAD_LEFT) . '_' .
                    str_pad($verse->chapter, 3, '0', STR_PAD_LEFT) . '_' .
                    str_pad($verse->verse, 3, '0', STR_PAD_LEFT) . '.mp3';

        $verse->file_name = $filename;

        $tts_class = self::$tts_apis[ config('audio.tts_api', 'narakeet') ] ?? null;

        if(!$tts_class) {
            return $this->addError('TTS API NOT supported: ' . config('audio.tts_api'));
        }

        $TTS = new $tts_class($Bible, $parameters);

        $success = $TTS->generateAudio($verse->text, $parameters, $filename);

        if(!$success) {
            if($TTS->hasErrors()) {
                return $this->mergeErrors($TTS);
            } else {
                return $this->addTransError('errors.audio.tts_failed', ['bcv' => $bcv]);
            }
        } else {
            $verse->file_name = $filename;
        }

        return $TTS->getGenerateDetails();
    }

    public function previewAudioFiles($module, $file_names, $match_option)
    {
        $results = [];

        natsort($file_names);

        foreach($file_names as $file) {
            $parsed = self::parseFilenameVerse($file, $match_option);

            if(!$parsed) {
                $results[] = [
                    'filename' => $file,
                    'success'  => false,
                    'parsed'   => null,
                    'error'    => 'Could not parse filename',
                ];
                continue;
            }

            $results[] = [
                'filename' => $file,
                'success'  => true,
                'parsed'   => $parsed,
            ];
        }

        return $results;
    }

    public function uploadAudioFiles($module, $files, $match_option, $overwrite_existing = false)
    {
        $results = [];

        foreach($files as $file) {
            $filename = $file->getClientOriginalName();

            $parsed = self::parseFilenameVerse($filename, $match_option);

            if(!$parsed) {
                $results[] = [
                    'filename' => $filename,
                    'success'  => false,
                    'error'    => 'Could not parse filename',
                ];
                continue;
            }

            // print_r($parsed); continue;

            if($parsed['type'] == 'chapter') {
                $ABB = AudioBibleVerse::where('module', $module)
                    ->where('book', $parsed['book'])
                    ->where('chapter', $parsed['chapter'])
                    ->whereNull('verse')
                    ->first();

                    $new_filename = str_pad($parsed['book'], 2, '0', STR_PAD_LEFT) . '_' .
                                    str_pad($parsed['chapter'], 3, '0', STR_PAD_LEFT) . '.mp3';
            } else {
                $ABB = AudioBibleVerse::where('module', $module)
                    ->where('book', $parsed['book'])
                    ->where('chapter', $parsed['chapter'])
                    ->where('verse', $parsed['verse'])
                    ->first();

                $new_filename = str_pad($parsed['book'], 2, '0', STR_PAD_LEFT) . '_' .
                                str_pad($parsed['chapter'], 3, '0', STR_PAD_LEFT) . '_' .
                                str_pad($parsed['verse'], 3, '0', STR_PAD_LEFT) . '.mp3';
            }

            if($ABB) {
                if($ABB->file_name) {
                    if($ABB->file_name == $new_filename && !$overwrite_existing) {
                        $results[] = [
                            'filename' => $filename,
                            'success'  => false,
                            'error'    => 'Audio already exists for this passage',
                        ];
                        continue;
                    }
                    
                    // delete old file
                    $old_path = TtsAbstract::getAudioFilePathStatic($module) . '/' . $ABB->file_name;

                    if(file_exists($old_path)) {
                        unlink($old_path);
                    }
                } else {
                    $ABB->file_name = $new_filename;
                }
            }

            // move uploaded file to audio dir
            $file->move( TtsAbstract::getAudioFilePathStatic($module), basename($new_filename) );

            if(!$ABB) {
                $ABB = new AudioBibleVerse();
                $ABB->module  = $module;
                $ABB->book    = $parsed['book'];
                $ABB->chapter = $parsed['chapter'];
                $ABB->verse   = $parsed['verse'];
                $ABB->file_name = $new_filename;
                $ABB->source  = 'upload';
                $ABB->voice   = null;
                $ABB->save();
            }

            $results[] = [
                'filename' => $filename,
                'success'  => true,
                'parsed'   => $parsed,
            ];
        }

        return $results;
    }

    /**
     * Attempts to parse a filename to extract book, chapter, verse info
     * based on known patterns.
     *
     * @param string $filename The filename to parse.
     * @param string|bool true $match specific match to use or true for auto-detect
     * @return array|null An associative array with parsed data or null if no match.
     */
    public static function parseFilenameVerse($filename, $option)
    {
        if(!$option || !$filename) {
            return null;
        }

        $auto = ($option === true || $option === 'auto');

        if(!$auto && !isset(self::$filename_matches[$option])) {
            return null;
        }

        $has_match = false;

        if($auto) {            
            foreach(self::$filename_matches as $key => $match) {
                if (!($match['auto'] ?? false)) {
                    continue;
                }
                
                if(preg_match($match['pattern'], $filename, $matches)) {
                    $has_match = true;
                    break;
                }
            }
        } else {
            $match = self::$filename_matches[$option];

            if(preg_match($match['pattern'], $filename, $matches)) {
                $has_match = true;
            }
        }

        if($has_match) {
            array_shift($matches); // remove full match

            $result = [
                'type' => $match['type'],
            ];

            if($match['type'] == 'verse') {
                $result['book']    = (int) ltrim($matches[0], '0');
                $result['chapter'] = (int) ltrim($matches[1], '0');
                $result['verse']   = (int) ltrim($matches[2], '0');
            } elseif($match['type'] == 'chapter') {
                $result['book']    = (int) ltrim($matches[0], '0');
                $result['chapter'] = (int) ltrim($matches[1], '0');
                $result['verse']   = null;
            }

            if($result['book'] < 1 || $result['book'] > config('bible.total_books') || $result['chapter'] < 1) {
                return null;
            }

            $chapters_in_book = config('bss.books_common')[$result['book']]['chapters'] ?? null;

            if($chapters_in_book === null || $result['chapter'] > $chapters_in_book) {
                return null;
            }

            return $result;
        }

        return null;
    }
}