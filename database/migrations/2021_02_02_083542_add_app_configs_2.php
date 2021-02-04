<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

class AddAppConfigs2 extends Migration
{
    private $config_items = [
        [
            'key'       => 'app.version_date',
            'descr'     => 'Application Version Date (hidden)',
            'default'   => NULL, // Automatically Set
            'global'    => 1,
            'type'      => 'string',
        ],        
        [
            'key'       => 'app.version_ustat',
            'descr'     => 'Application USTAT (hidden - for future use)',
            'default'   => NULL, 
            'global'    => 1,
            'type'      => 'string',
        ],        
        [
            'key'       => 'app.version_utest',
            'descr'     => 'Application UTEST (hidden - for future use)',
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
