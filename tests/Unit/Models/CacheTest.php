<?php

namespace Tests\Unit\Models;

use App\Models\Cache;
use Tests\TestCase;

class CacheTest extends TestCase
{

    public function testInstance()
    {
        $cache = new Cache();
        $this->assertInstanceOf(Cache::class, $cache);
    }
}