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
            'key'       => 'download.app_link_enable',
            'descr'     => 'Add link back to this local install of Bible SuperSearch API to copyright info',
            'default'   => FALSE,
            'global'    => 1,
            'type'      => 'bool',
        ],
        [
            'key'       => 'download.bss_link_enable',
            'descr'     => 'Add link back to BibleSuperSearch.com API (api.biblesuper) to copyright info',
            'default'   => FALSE,
            'global'    => 1,
            'type'      => 'bool',
        ],
        [
            'key'       => 'download.cache.temp_cache_size',
            'descr'     => 'Download Temporary Cache Size (MB)',
            'default'   => 50,
            'global'    => 1,
            'type'      => 'int',
        ],
        [
            'key'       => 'download.cache.cache_size',
            'descr'     => 'Download Retained File Cache Size (MB)',
            'default'   => 50,
            'global'    => 1,
            'type'      => 'int',
        ],        
        [
            'key'       => 'download.cache.min_render_time',
            'descr'     => 'Download Retained File Minimum Render Time (seconds)',
            'default'   => 60,
            'global'    => 1,
            'type'      => 'int',
        ],        
        [
            'key'       => 'download.cache.min_hits',
            'descr'     => 'Download Retained File Minimum Hits (downloads)',
            'default'   => 60,
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
