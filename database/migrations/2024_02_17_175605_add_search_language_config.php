<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

return new class extends Migration
{
    private $config_items = [
        [
            'key'       => 'bss.parallel_search_different_languages',
            'descr'     => 'Parallel Search Across Bibles of Differing Languages',
            'default'   => 'never',
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
