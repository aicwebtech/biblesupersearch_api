<?php

namespace Tests\Unit\Events;

use PHPUnit\Framework\TestCase;
use App\Events\SomeEvent;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * SomeEvent is the framework's scaffolded event, still referenced by the broadcast wiring.
 * These tests pin the contract the broadcaster relies on.
 */
class SomeEventTest extends TestCase
{
    public function testItConstructs(): void
    {
        $this->assertInstanceOf(SomeEvent::class, new SomeEvent());
    }

    public function testBroadcastsOnAPrivateChannel(): void
    {
        $event = new SomeEvent();

        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
    }

    public function testItIsDispatchable(): void
    {
        $this->assertContains(Dispatchable::class, class_uses(SomeEvent::class));
    }
}
