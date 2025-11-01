<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

return new class extends Migration
{
    private $config_items = [
        [
            'key'       => 'audio.enable',
            'descr'     => 'Enable Bible Audio (globally)',
            'default'   => false,
            'global'    => 1,
            'type'      => 'bool',
        ],        
        [
            'key'       => 'audio.tts_api_enable',
            'descr'     => 'Enable TTS API for audio generation',
            'default'   => false,
            'global'    => 1,
            'type'      => 'bool',
        ],        
        [
            'key'       => 'audio.tts_api',
            'descr'     => 'TTS API Selection',
            'default'   => null,
            'global'    => 1,
            'type'      => 'string',
        ],        
        [
            'key'       => 'audio.tts_api_key',
            'descr'     => 'TTS API Key',
            'default'   => null,
            'global'    => 1,
            'type'      => 'string',
        ],        
        [
            // Is this needd? 
            // Maybe just assume verse if tts_api_enable is true?
            // And chapter if false?
            'key'       => 'audio.granularity',
            'descr'     => 'Audio granularity (chapter or verse)',
            'default'   => 'chapter',
            'global'    => 1,
            'type'      => 'string',
        ],
    ];


    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        ConfigManager::addConfigItems($this->config_items);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ConfigManager::removeConfigItems($this->config_items);
    }
};
