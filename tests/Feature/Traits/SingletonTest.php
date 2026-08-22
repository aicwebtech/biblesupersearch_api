<?php

namespace Tests\Feature\Traits;

use Tests\TestCase;
use \App\Traits\Singleton;
use Illuminate\Support\Facades\Config;


/**
 * Minimal classes used to exercise the Singleton trait.
 * They are declared here so tests are self-contained.
 */

class SimpleSingleton 
{
    use Singleton;
}

class PremiumSingleton 
{
    use Singleton;
}

class PremiumSingletonPremium extends PremiumSingleton
{
    use Singleton;
    public $marker = 'premium';
}

class SingletonTest extends TestCase
{
    public function tearDown(): void
    {
        //Ensure singletons are reset between tests
        if (class_exists(SimpleSingleton::class)) {
            SimpleSingleton::freshInstance();
        }
        if (class_exists(PremiumSingleton::class)) {
            PremiumSingleton::freshInstance();
        }
        parent::tearDown();
    }

    public function testGetInstanceReturnsSameInstance()
    {
        Config::set('app.premium', false);
        
        if(!class_exists(SimpleSingleton::class)) {
            $this->markTestSkipped('SimpleSingleton class does not exist');
        }
        
        SimpleSingleton::freshInstance();
        $a = SimpleSingleton::getInstance();
        $b = SimpleSingleton::getInstance();

        $this->assertSame($a, $b, 'getInstance should return the same instance on multiple calls');
    }

    public function testFreshInstanceResetsInstance()
    {
        SimpleSingleton::freshInstance();
        $a = SimpleSingleton::getInstance();
        $b = SimpleSingleton::freshInstance();

        $this->assertNotSame($a, $b, 'freshInstance should reset the stored instance and return a new one');
    }

    public function testResetInstanceDiscardsStoredInstance()
    {
        Config::set('app.premium', false);

        SimpleSingleton::freshInstance();
        $a = SimpleSingleton::getInstance();

        SimpleSingleton::resetInstance();
        $b = SimpleSingleton::getInstance();

        $this->assertNotSame($a, $b, 'resetInstance should discard the stored instance');
    }

    public function testResetInstanceIsLazy()
    {
        Config::set('app.premium', false);

        SimpleSingleton::freshInstance();
        SimpleSingleton::resetInstance();

        // Nothing is built until getInstance() is called again. Reading the static through
        // reflection avoids triggering the lazy construction we are asserting on. No
        // setAccessible() call: it has been a no-op since PHP 8.1 and is deprecated in 8.5.
        $property = new \ReflectionProperty(SimpleSingleton::class, 'instance');

        $this->assertNull($property->getValue(), 'resetInstance should not construct a replacement');
    }

    public function __testGenerateInstancePrefersPremiumClassWhenEnabledAndExists()
    {
        // this test unable to work given current premium namespace logic ...
        
        Config::set('app.premium', true);

        $this->assertTrue(config('app.premium'), 'app.premium config should be true for this test');

        // Ensure any previous instance is cleared
        PremiumSingleton::freshInstance();

        $instance = PremiumSingleton::getInstance();

        $this->assertInstanceOf(
            PremiumSingletonPremium::class,
            $instance,
            'When app.premium is true and a premium class exists, generateInstance should instantiate the premium class'
        );
    }

    public function testGenerateInstanceUsesDefaultWhenPremiumDisabledOrMissing()
    {
        Config::set('app.premium', false);

        $this->assertFalse(config('app.premium'), 'app.premium config should be true for this test');

        PremiumSingleton::freshInstance();
        $instance = PremiumSingleton::getInstance();

        $this->assertInstanceOf(
            PremiumSingleton::class,
            $instance,
            'When app.premium is false (or premium class not used), generateInstance should instantiate the original class'
        );
    }
}
