<?php

namespace App\Listeners;

use App\Events\ConfigErrorEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\ConfigManager;

class ConfigErrorListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ConfigErrorEvent  $event
     * @return void
     */
    public function handle(ConfigErrorEvent $event)
    {
        $user_id = $event->global ? 0 : $event->user_id;

        // Attempt to reset / default some settings
        $config = ConfigManager::getConfigs($user_id);
        $config['bss.daily_access_limit'] = 1000;
        // $config['bss.pagination_limit'] = 1000;
        $config['app.version_utest'] = rand(100000, 999999);

        ConfigManager::setConfigs($config, $user_id);
    }
}
