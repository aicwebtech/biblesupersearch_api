<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;

// Named test double so the same class is used for all tests in this file
class SingletonSubject
{
    use \App\Traits\Singleton;

    // Override generateInstance so we don't touch config('app.premium')
    public static function generateInstance()
    {
        return new static();
    }
}

class SingletonTest extends TestCase
{
    protected function setUp(): void
    {
        // Start each test with a fresh (null) singleton so tests are independent
        SingletonSubject::freshInstance();
    }

    public function testGetInstanceReturnsSameObjectEachCall(): void
    {
        $a = SingletonSubject::getInstance();
        $b = SingletonSubject::getInstance();

        $this->assertSame($a, $b);
    }

    public function testFreshInstanceReturnsNewObject(): void
    {
        $first  = SingletonSubject::getInstance();
        $second = SingletonSubject::freshInstance();

        $this->assertNotSame($first, $second);
    }

    public function testGetInstanceAfterFreshInstanceReturnsSameNewObject(): void
    {
        $fresh = SingletonSubject::freshInstance();
        $again = SingletonSubject::getInstance();

        $this->assertSame($fresh, $again);
    }

    public function testGetInstanceReturnsCorrectType(): void
    {
        $instance = SingletonSubject::getInstance();
        $this->assertInstanceOf(SingletonSubject::class, $instance);
    }

    /**
     * resetInstance() returns nothing - callers rely on the lazy getInstance() that follows,
     * not on a return value. The discard and laziness themselves are asserted in
     * Tests\Feature\Traits\SingletonTest; this file only adds what runs with no application.
     */
    public function testResetInstanceReturnsNothing(): void
    {
        $this->assertNull(SingletonSubject::resetInstance());
    }

    public function testResetInstanceIsSafeWhenNoInstanceExists(): void
    {
        SingletonSubject::resetInstance();
        SingletonSubject::resetInstance();

        $this->assertInstanceOf(SingletonSubject::class, SingletonSubject::getInstance());
    }
}
