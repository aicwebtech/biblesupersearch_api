<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\ConfigManager;

class AddAppVerisonCacheConfig extends Migration
{
    
    private $config_items = [
        [
            'key'       => 'app.version_cache',
            'descr'     => 'Application Version Cache - Used to trigger updates',
            'default'   => '4.0.0',
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
}
