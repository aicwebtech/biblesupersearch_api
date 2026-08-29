<?php

namespace Tests\Feature\Providers;

use App\Providers\ConfigServiceProvider;
use Tests\TestCase;

/**
 * ConfigServiceProvider is registered in config/app.php but does almost nothing: boot() is
 * empty and register() makes a single config() call. These tests pin that it stays harmless -
 * it runs on every request, so a throw here would take the whole application down.
 */
class ConfigServiceProviderTest extends TestCase
{
    public function testRegisterRunsWithoutThrowing(): void
    {
        $provider = new ConfigServiceProvider($this->app);

        $this->assertNull($provider->register());
    }

    public function testBootRunsWithoutThrowing(): void
    {
        $provider = new ConfigServiceProvider($this->app);

        $this->assertNull($provider->boot());
    }

    /**
     * register() must not disturb configuration the rest of the application depends on.
     */
    public function testRegisterLeavesExistingConfigurationIntact(): void
    {
        $before = config('app.key');

        (new ConfigServiceProvider($this->app))->register();

        $this->assertSame($before, config('app.key'));
    }
}
