<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\CacheManager;
use App\Models\Cache;

class CacheManagerTest extends TestCase
{

    public function testCreateCache()
    {
        $manager = new CacheManager();
        $form_data = ['b' => 2, 'a' => 1];

        $cache = $manager->createCache($form_data);

        $this->assertInstanceOf(Cache::class, $cache);
    }

}