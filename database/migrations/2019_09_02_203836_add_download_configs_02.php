<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use aicwebtech\BibleSuperSearch\ConfigManager;

class AddDownloadConfigs02 extends Migration
{
    private $config_items = [
        [
            'key'       => 'download.derivative_copyright_statement',
            'descr'     => 'Download Derivate Copyright Statement',
            'default'   => '',
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
