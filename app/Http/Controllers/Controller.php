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

    /**
     * Serialise an admin bootstrap object for embedding in a <script> block.
     *
     * The views emit this raw (`var bootstrap = @php echo $bootstrap @endphp;`),
     * so the JSON_HEX_* flags keep any admin-supplied string content (copyright
     * statements, language names, importer descriptions) from terminating the
     * script element or breaking out of the assignment.
     *
     * @param  \stdClass  $bootstrap
     * @return string
     */
    protected function encodeBootstrap($bootstrap): string
    {
        return json_encode($bootstrap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

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

        // Shared with the Bible edit form so its module rule matches
        // Bible::validateModule() rather than keeping a second copy in JS.
        $bootstrap->php_reserved_words = \App\Helpers::phpReservedWords();

        $bootstrap->book_lists = new \stdClass();

        $bootstrap->book_lists->en = \App\Models\Books\En::get();

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
