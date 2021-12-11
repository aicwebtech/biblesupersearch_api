<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\ConfigManager;

class AddDownloadConfigs extends Migration
{
    private $config_items = [
        [
            'key'       => 'download.enable',
            'descr'     => 'Downloads Enabled',
            'default'   => TRUE,
            'global'    => 1,
            'type'      => 'bool',
        ],
        [
            'key'       => 'download.tab_enable',
            'descr'     => 'Downloads Tab Enabled',
            'default'   => TRUE,
            'global'    => 1,
            'type'      => 'bool',
        ],
        [
            'key'       => 'download.cache.days',
            'descr'     => 'Download file cache days',
            'default'   => 30,
            'global'    => 1,
            'type'      => 'int',
        ],
        [
            'key'       => 'download.cache.max_filesize',
            'descr'     => 'Download file cache max filesize',
            'default'   => 10,
            'global'    => 1,
            'type'      => 'int',
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
