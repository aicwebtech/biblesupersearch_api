<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

class AddDownloadLimit extends Migration
{
    private $config_items = [
        [
            'key'       => 'download.bible_limit',
            'descr'     => 'Maximum Number of Bibles that can be downloaded at once.  0 = no limit',
            'default'   => 15,
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
