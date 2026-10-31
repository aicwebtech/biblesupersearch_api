<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\CacheManager;
use App\Models\Cache;

/**
 * The cache rows these tests write are committed to the shared database, so each test uses form
 * data no real request sends (a uniqid) and removes its row again by hash_long in tearDown.
 */
class CacheManagerTest extends TestCase
{
    /**
     * Form data unique to this test, with its cache row removed in tearDown
     *
     * @return array<string, mixed>
     */
    private function uniqueFormData(string $key): array
    {
        $form_data = [$key => uniqid('cache_manager_test_', true)];
        $hash_long = md5(json_encode($form_data));

        $this->deferCleanup(fn() => Cache::where('hash_long', $hash_long)->delete());

        return $form_data;
    }

    public function testCreateCache(): void
    {
        $manager = new CacheManager();
        $value   = uniqid('cache_manager_test_', true);

        $this->deferCleanup(fn() => Cache::where('hash_long', md5(json_encode(['a' => 1, 'b' => $value])))->delete());

        $cache = $manager->createCache(['b' => $value, 'a' => 1]);

        $this->assertInstanceOf(Cache::class, $cache);
        $this->assertNotEmpty($cache->hash);
        $this->assertEquals(md5(json_encode(['a' => 1, 'b' => $value])), $cache->hash_long);
    }

    /**
     * The INSERT is deferred off the request's critical path: the row must not exist until the
     * application terminates (after the response is sent), then it is persisted exactly once.
     */
    public function testCreateCacheDefersWrite(): void
    {
        $manager = new CacheManager();
        $form_data = $this->uniqueFormData('deferred');

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
    public function testConcurrentIdenticalRequestsShareResolvableHash(): void
    {
        $manager = new CacheManager();
        $form_data = $this->uniqueFormData('racy');

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
    public function testCreateCacheReusesExisting(): void
    {
        $manager = new CacheManager();
        $form_data = $this->uniqueFormData('reused');

        $first = $manager->createCache($form_data);
        $this->app->terminate();

        $second = $manager->createCache($form_data);

        $this->assertEquals($first->hash, $second->hash);
        $this->assertEquals(1, Cache::where('hash', $first->hash)->count());
    }
}