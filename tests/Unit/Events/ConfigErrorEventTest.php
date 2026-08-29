<?php

namespace Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use App\Events\ConfigErrorEvent;
use App\User;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * ConfigErrorEvent carries the id of the user whose config write failed.
 *
 * The event is a plain value object: it reads one property off the User it is handed and
 * never persists anything, so it is exercised here without a database.
 */
class ConfigErrorEventTest extends TestCase
{
    public function testConstructorCopiesTheUserId(): void
    {
        $user     = new User();
        $user->id = 42;

        $event = new ConfigErrorEvent($user);

        $this->assertSame(42, $event->user_id);
    }

    /**
     * An unsaved User has no id yet. The event must still construct rather than fatal,
     * because a config error can be raised before the user row is ever written.
     */
    public function testConstructorHandlesAUserWithNoIdYet(): void
    {
        $event = new ConfigErrorEvent(new User());

        $this->assertNull($event->user_id);
    }

    public function testGlobalDefaultsToTrue(): void
    {
        $event = new ConfigErrorEvent(new User());

        $this->assertTrue($event->global);
    }

    public function testBroadcastsOnAPrivateChannel(): void
    {
        $event = new ConfigErrorEvent(new User());

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
    }
}
