<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use aicwebtech\BibleSuperSearch\ConfigManager;

class CreateSoftConfigTable extends Migration
{
    private $config_items = [
        [
            'key'       => 'app.name',
            'descr'     => 'Application Name',
            'default'   => 'Bible SuperSearch API',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'app.config_cache',
            'descr'     => 'Config Caching Enabled',
            'default'   => FALSE,
            'global'    => 1,
            'type'      => 'bool',
        ],
        [
            'key'       => 'bss.defaults.bible',
            'descr'     => 'Default Bible Version',
            'default'   => 'kjv',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'bss.defaults.highlight_tag',
            'descr'     => 'Default Highlight Tag',
            'default'   => 'b',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'mail.from.name',
            'descr'     => 'System Mail Name',
            'default'   => 'Bible SuperSearch',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'mail.from.address',
            'descr'     => 'System Mail Address',
            'default'   => 'email@example.com',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'app.client_url',
            'descr'     => 'Client URL',
            'default'   => NULL,
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'bss.daily_access_limit',
            'descr'     => 'Daily Access Limit (No Key)',
            'default'   => 1000,
            'global'    => 1,
            'type'      => 'int',
        ],
        [
            'key'       => 'bss.pagination.limit',
            'descr'     => 'Verses Per Page',
            'default'   => 30,
            'global'    => 1,
            'type'      => 'int',
        ],
        [
            'key'       => 'bss.global_maximum_results',
            'descr'     => 'Maximum Overall Results',
            'default'   => 500,
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
        Schema::create('configs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key', 200);
            $table->string('descr', 200);
            $table->string('type', 100)->nullable();
            $table->tinyInteger('global')->default(1);
            $table->longText('default')->nullable();
            $table->unique('key');
        });

        Schema::create('config_values', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('config_id');
            $table->unsignedInteger('user_id');
            $table->longText('value')->nullable();
            $table->unique(['config_id', 'user_id'], 'idx_uc');
        });

        ConfigManager::addConfigItems($this->config_items);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // For future config rollbacks, we will do something like this:
        // ConfigManager::removeConfigItems($this->config_items);

        Schema::drop('configs');
        Schema::drop('config_values');
    }
}
