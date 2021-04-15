<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

class AddLcConfig extends Migration
{
    private $config_items = [
        [
            'key'       => 'lc.key',
            'descr'     => 'LC Key',
            'default'   => NULL,
            'global'    => 1,
            'type'      => 'string',
        ],        
        [
            'key'       => 'lc.date',
            'descr'     => 'LC Date',
            'default'   => NULL, 
            'global'    => 1,
            'type'      => 'string',
        ],        
        [
            'key'       => 'lc.confirm',
            'descr'     => 'LC Confirm',
            'default'   => NULL,
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
