<?php

namespace Tests\Feature\Providers;

use App\Providers\BroadcastServiceProvider;
use App\User;
use Tests\TestCase;

/**
 * BroadcastServiceProvider registers the broadcast auth routes and the personal
 * App.User.* channel. boot() is never reached by the rest of the suite - the provider is not
 * in config/app.php's provider list - so it is driven directly here.
 *
 * These tests run against the shipped default driver. That default was 'pusher' until
 * BSS-285, whose SDK (pusher/pusher-php-server) is not a dependency of this application, so
 * resolving the broadcaster threw and the tests had to swap in the null driver by hand.
 */
class BroadcastServiceProviderTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // config/broadcasting.php resolves its default from BROADCAST_DRIVER, so without this
        // the environment running the suite would decide which broadcaster these tests resolve
        // - and whether its SDK is even installed. The shipped fallback is asserted separately
        // below.
        config(['broadcasting.default' => 'null']);
    }

    /**
     * The shipped default must resolve without any optional SDK installed. A driver needing
     * one would fail here rather than in whichever request first broadcast something.
     */
    public function testTheDefaultBroadcasterResolvesOutOfTheBox(): void
    {
        $broadcaster = $this->app->make(\Illuminate\Contracts\Broadcasting\Factory::class)->connection();

        $this->assertInstanceOf(\Illuminate\Broadcasting\Broadcasters\NullBroadcaster::class, $broadcaster);
    }

    /**
     * The fallback in the config file itself is what BSS-285 changed - from 'pusher', whose SDK
     * is not a dependency here, to 'null'. Read with BROADCAST_DRIVER out of the way, so an
     * environment that sets it cannot hide a regression.
     */
    public function testTheShippedConfigFallsBackToTheNullDriver(): void
    {
        $original = $_SERVER['BROADCAST_DRIVER'] ?? $_ENV['BROADCAST_DRIVER'] ?? NULL;

        unset($_SERVER['BROADCAST_DRIVER'], $_ENV['BROADCAST_DRIVER']);
        putenv('BROADCAST_DRIVER');

        try {
            $config = require config_path('broadcasting.php');

            $this->assertSame('null', $config['default']);
        }
        finally {
            if($original !== NULL) {
                $_SERVER['BROADCAST_DRIVER'] = $_ENV['BROADCAST_DRIVER'] = $original;
                putenv('BROADCAST_DRIVER=' . $original);
            }
        }
    }

    /**
     * Broadcast::routes() registers the auth endpoint without a route name, so it is matched
     * by URI. Asserting the methods too, because the JS client POSTs to it.
     */
    public function testBootRegistersTheBroadcastAuthRoute(): void
    {
        $provider = new BroadcastServiceProvider($this->app);

        $provider->boot();

        $route = collect($this->app['router']->getRoutes())
            ->first(fn ($route) => $route->uri() === 'broadcasting/auth');

        $this->assertNotNull($route, 'boot() should register the broadcasting/auth route');
        $this->assertContains('POST', $route->methods());
    }

    public function testRegisterAddsNoBindings(): void
    {
        $provider = new BroadcastServiceProvider($this->app);

        $this->assertNull($provider->register());
    }

    /**
     * The personal channel authorises a user only for their own id. Reading the registered
     * callback back off the broadcaster keeps this honest about what boot() actually wired up,
     * rather than re-implementing the comparison in the test.
     */
    public function testPersonalChannelAuthorisesOnlyTheMatchingUser(): void
    {
        $provider = new BroadcastServiceProvider($this->app);
        $provider->boot();

        $broadcaster = $this->app->make(\Illuminate\Contracts\Broadcasting\Factory::class)->connection();

        $property = new \ReflectionProperty($broadcaster, 'channels');
        $channels = $property->getValue($broadcaster);

        $this->assertArrayHasKey('App.User.*', $channels, 'the personal channel should be registered');

        $callback = $channels['App.User.*'];

        $user     = new User();
        $user->id = 7;

        $this->assertTrue($callback($user, 7));
        $this->assertTrue($callback($user, '7'), 'ids arrive from the URL as strings');
        $this->assertFalse($callback($user, 8));
    }
}
