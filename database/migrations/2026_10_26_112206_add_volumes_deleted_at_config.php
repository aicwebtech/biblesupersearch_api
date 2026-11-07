<?php

use Illuminate\Database\Migrations\Migration;
use App\ConfigManager;

return new class extends Migration
{
    /**
     * When a volume was last deleted: statics_changed reports it, as a deleted row takes its
     * updated_at with it.  See Volume::recordDeletion().
     */
    private array $config_items = [
        [
            'key'       => 'app.volumes_deleted_at',
            'descr'     => 'Volume Last Deleted At',
            'default'   => 0,
            'global'    => 1,
            'type'      => 'int',
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        ConfigManager::addConfigItems($this->config_items);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        ConfigManager::removeConfigItems($this->config_items);
    }
};
