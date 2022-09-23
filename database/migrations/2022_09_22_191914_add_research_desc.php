<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\ConfigManager;

return new class extends Migration
{
        private $config_items = [
        [
            'key'       => 'bss.research_description',
            'descr'     => 'Research Only Bible Description',
            'default'   => 'Intended for research purposes only.  These Bibles are not based on the Textus Receptus and/or are not Formal Equivalence translations, and are therefore not recommended for other uses.',
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
