<?php

namespace Tests\Feature\Providers;

use App\Providers\BroadcastServiceProvider;
use App\User;
use Tests\TestCase;

/**
 * BroadcastServiceProvider registers the broadcast auth routes and the personal
 * App.User.* channel. boot() is never reached by the rest of the suite, so it is driven
 * directly here.
 *
 * The configured default driver is pusher, whose SDK (pusher/pusher-php-server) is not a
 * dependency of this application - resolving it throws. These tests therefore pin the
 * provider's own wiring against the null broadcaster, which needs no SDK.
 */
class BroadcastServiceProviderTest extends TestCase
{
    /** Swaps in a broadcaster that has no external SDK requirement. */
    private function useNullBroadcaster(): void
    {
        config(['broadcasting.default' => 'null']);

        $this->app->forgetInstance(\Illuminate\Contracts\Broadcasting\Factory::class);
    }

    public function testBootRegistersTheBroadcastWiring(): void
    {
        $this->useNullBroadcaster();

        $provider = new BroadcastServiceProvider($this->app);

        $provider->boot();

        $this->assertTrue(
            $this->app['router']->getRoutes()->hasNamedRoute('broadcasting.auth')
            || collect($this->app['router']->getRoutes())->contains(
                fn ($route) => $route->uri() === 'broadcasting/auth'
            ),
            'boot() should register the broadcasting auth route'
        );
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
        $this->useNullBroadcaster();

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
