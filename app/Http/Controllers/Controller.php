<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct() {}

    protected function getAdminBootstrap()
    {
        $ImportManagerClass = \App\Helpers::find('\App\ImportManager');
        
        $bootstrap = new \stdClass();
        $bootstrap->baseURL = url('');
        
        $bootstrap->devToolsEnabled  = (bool) config('bss.dev_tools');
        $bootstrap->premToolsEnabled = config('app.premium');
        $bootstrap->maxUploadSize    = \App\Helpers::maxUploadSize('both');
        $bootstrap->importers  = $ImportManagerClass::getImportersList();

        $bootstrap->audio_enabled = (bool)config('audio.enable', false);
        $bootstrap->tts_enabled   = (bool)config('audio.enable', false) && (bool)config('audio.tts_api_enable', false);
        $bootstrap->tts_apis = \App\AudioManager::getTtsApisList();
        $bootstrap->tts_api_default = config('audio.tts_api') ?? null;
        $bootstrap->tts_filename_matches = \App\AudioManager::getFilenameMatchesList();

        $bootstrap->languages  = \App\Models\Language::orderBy('name', 'asc')->get();

        foreach($bootstrap->languages as &$language) {
            $language['tts_api_voices'] = \App\TextToSpeech\TtsAbstract::getAllApiVoicesByLanguage($language['code'], $language['tts_api']);
        }
        unset($language);

        $bootstrap->copyrights = [];

        foreach(\App\Models\Copyright::orderBy('name')->get() as $Copyright) {
            $data = $Copyright->getAttributes();
            $data['copyright_statement_processed'] = $Copyright->getProcessedCopyrightStatement();
            $bootstrap->copyrights[] = $data;
        }

        return $bootstrap;
    }
}
