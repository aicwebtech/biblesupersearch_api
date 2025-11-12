<?php

namespace App\TextToSpeech;

use App\Models\Bible;
use App\Models\AudioBibleVerse;
use DB;
use App;

abstract class TtsAbstract 
{
    use \App\Traits\Error;

    static public $name;
    static public $description = '';

    public $debug = false; // Debug rendering by only rendering handful of verses

    protected $file_extension = 'mp3';

    protected $Bible;
    protected $chunk_size = 100;

    protected $current_book    = NULL;
    protected $current_chapter = NULL;
    protected $chunk_data = [];

    protected $Rendering = NULL;
    protected $overwrite = false;

    protected $connection_map = [];

    public function __construct($module) 
    {
        $this->Bible = ($module instanceof Bible) ? $module : Bible::findByModule($module);

        if(!$this->Bible) {
            $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
        }

        if(!$this->file_extension) {
            throw new Exception('$this->file_extension is required on render class!');
        }
    }

    public function generateAudio($text, $options = [], $filename = null) 
    {
        $apikey = config('audio.tts_api_key');
        $path = $this->getAudioFilePath(true);

        if($filename) {
            $file_path = $path . '/' . $filename;
        } else {
            $file_path = $path . '/narakeet_' . md5($text . microtime(false)) . '.mp3';
        }

        try {
            $file_handle = fopen($file_path, 'w');
            $text = $this->_formatText($text);

            $success = $this->generateAudioHelper($text, $options, $file_handle);

            fclose($file_handle);

            return $success;
        } catch (\Exception $e) {
            $this->addError( $e->getMessage() );
            return FALSE;
        }
    }

    abstract protected function generateAudioHelper($text, $options, $file_handle);

    protected function _formatText($text)
    {
        $text = preg_replace('/\} \{/', '', $text);
        $text = preg_replace('/\{[^\}]+\}/', '', $text);
        $text = str_replace(['[', ']'], '', $text);
        $text = str_replace(['‹', '›'], '', $text);

        $text = trim($text);

        return $text;
    }

    protected function _getBookTable() 
    {
        if($this->book_name_language_force) {
            return 'books_' . $this->book_name_language_force;
        }

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($this->Bible->lang_short)) {
            return 'books_' . $this->Bible->lang_short;
        }

        $lang = config('bss.defaults.language_short');

        if (\App\Models\Books\BookAbstract::isSupportedLanguage($lang)) {
            return 'books_' . $lang;
        }

        return 'books_en';
    }

    public function getAudioFilePath($create_dir = FALSE, $relative = false) 
    {
        if($this->hasErrors()) {
            return FALSE;
        }

        $dir = $relative ? '' : static::getAudioBasePath();
        $dir .= $this->Bible->module;

        if(!is_dir($dir) && $create_dir && !$relative) {
            mkdir($dir, 0775, TRUE);
            chmod($dir, 0775);
        }

        return $dir;
    }

    public static function getAudioBasePath() 
    {
        return dirname(__FILE__) . '/../../bibles/audio/';
    }

    public static function getVoiceByLanguage($language_short, $tts_api = null)
    {
        if(!$tts_api) {
            $tts_api = strtolower( (new \ReflectionClass(static::class))->getShortName() );
        }

        // :todo let user select voice by language?
        // :todo let user select male vs female voice?

        $voice = config('lang.' . $language_short . '.text_to_speech.' . $tts_api . '.voices.default');

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
