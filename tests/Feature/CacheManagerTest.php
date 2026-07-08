<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\CacheManager;
use App\Models\Cache;

class CacheManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function testCreateCache()
    {
        $manager = new CacheManager();
        $form_data = ['b' => 2, 'a' => 1];

        $cache = $manager->createCache($form_data);

        $this->assertInstanceOf(Cache::class, $cache);
        $this->assertNotEmpty($cache->hash);
        $this->assertEquals(md5(json_encode(['a' => 1, 'b' => 2])), $cache->hash_long);
    }

    /**
     * The INSERT is deferred off the request's critical path: the row must not exist until the
     * application terminates (after the response is sent), then it is persisted exactly once.
     */
    public function testCreateCacheDefersWrite()
    {
        $manager = new CacheManager();
        $form_data = ['deferred' => uniqid('', true)];

        $cache = $manager->createCache($form_data);
        $hash = $cache->hash;

        // Write has been deferred - nothing is persisted yet within the request.
        $this->assertNull(Cache::where('hash', $hash)->first());

        // Firing the app's terminating callbacks (as the kernel does post-response) persists it.
        $this->app->terminate();

        $this->assertNotNull(Cache::where('hash', $hash)->first());
    }

    /**
     * Race guard: two concurrent identical requests, both deferred and neither persisted yet,
     * must return the *same* deterministic hash. Otherwise the racer whose deferred INSERT loses
     * on the hash_long unique index would hand back a hash that never gets persisted. After the
     * writes fire, exactly one row exists and it resolves the hash both racers returned.
     */
    public function testConcurrentIdenticalRequestsShareResolvableHash()
    {
        $manager = new CacheManager();
        $form_data = ['racy' => uniqid('', true)];

        // No terminate() between the two calls => both writes are still pending (the race).
        $first  = $manager->createCache($form_data);
        $second = $manager->createCache($form_data);

        $this->assertEquals($first->hash, $second->hash);

        $this->app->terminate();

        $this->assertEquals(1, Cache::where('hash', $first->hash)->count());
        $this->assertNotNull($manager->getCacheByHash($second->hash));
    }

    /**
     * Identical form data reuses the already-persisted cache row rather than writing a new one.
     */
    public function testCreateCacheReusesExisting()
    {
        $manager = new CacheManager();
        $form_data = ['reused' => uniqid('', true)];

        $first = $manager->createCache($form_data);
        $this->app->terminate();

        $second = $manager->createCache($form_data);

        $this->assertEquals($first->hash, $second->hash);
        $this->assertEquals(1, Cache::where('hash', $first->hash)->count());
    }
}