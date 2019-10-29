<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\ConfigManager;

class AddDownloadConfigs3 extends Migration
{
    // NOT finalized as of 10.28.19 - may need to revert migration!

    private $config_items = [
        [
            'key'       => 'download.enable',
            'descr'     => 'Downloads Enabled',
            'default'   => FALSE,
            'global'    => 1,
            'type'      => 'bool',
        ],
        [
            'key'       => 'download.tab_enable',
            'descr'     => 'Downloads Tab Enabled',
            'default'   => FALSE,
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
            'descr'     => 'Maximum Retained Space',
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
