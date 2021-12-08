<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

class AddDownloadRetainConfigs extends Migration
{
    private $config_items = [
        [
            'key'       => 'download.retain',
            'descr'     => 'Retain rendered files',
            'default'   => FALSE,
            'global'    => 1,
            'type'      => 'bool',
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

        // This works, but 
        if(config('download.enable')) {
            ConfigManager::setConfig('download.retain', TRUE);
        }
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
