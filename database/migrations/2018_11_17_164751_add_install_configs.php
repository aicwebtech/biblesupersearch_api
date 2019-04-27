<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\ConfigManager;

class AddInstallConfigs extends Migration
{
    private $config_items = [
        [
            'key'       => 'app.installed',
            'descr'     => 'Aplication Installed',
            'default'   => FALSE,
            'global'    => 1,
            'type'      => 'bool',
        ],
        [
            'key'       => 'mail.driver',
            'descr'     => 'Mail Driver',
            'default'   => 'sendmail',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'mail.host',
            'descr'     => 'Mail Host',
            'default'   => '',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'mail.port',
            'descr'     => 'Mail Port',
            'default'   => 587,
            'global'    => 1,
            'type'      => 'int',
        ],
        [
            'key'       => 'mail.encryption',
            'descr'     => 'Mail Encryption',
            'default'   => 'tls',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'mail.username',
            'descr'     => 'Mail Username',
            'default'   => NULL,
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'mail.password',
            'descr'     => 'Mail Password',
            'default'   => NULL,
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'mail.sendmail',
            'descr'     => 'Path to Sendmail',
            'default'   => '/usr/sbin/sendmail -bs',
            'global'    => 1,
            'type'      => 'string',
        ],
        [
            'key'       => 'app.url',
            'descr'     => 'Application URL',
            'default'   => 'http://example.com',
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
