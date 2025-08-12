<?php

namespace Tests\Feature\Models;

use App\Models\Cache;
use Tests\TestCase;

class CacheTest extends TestCase
{

    public function testTableName()
    {
        $cache = new Cache();
        $this->assertEquals('cache', $cache->getTable());
    }
    
    public function testCreate()
    {
        $data = [
            'hash' => 'abc1234',
            'hash_long' => 'abc123def456',
            'form_data' => json_encode(['foo' => 'bar']),
            'preserve' => true,
        ];

        $cache = Cache::create($data);

        $this->assertDatabaseHas('cache', [
            'hash' => 'abc1234',
            'hash_long' => 'abc123def456',
            'form_data' => json_encode(['foo' => 'bar']),
            'preserve' => true,
        ]);

        $cache->delete();
    }

    public function testUpdate()
    {
        $cache = Cache::create([
            'hash' => 'xyz7890',
            'hash_long' => 'xyz789ghi012',
            'form_data' => json_encode(['baz' => 'qux']),
            'preserve' => false,
        ]);

        $cache->update(['preserve' => true]);

        $this->assertDatabaseHas('cache', [
            'hash' => 'xyz7890',
            'preserve' => true,
        ]);

        $cache->delete();
    }

    public function testDelete()
    {
        $cache = Cache::create([
            'hash' => 'del12345',
            'hash_long' => 'del12345long',
            'form_data' => json_encode(['delete' => 'me']),
            'preserve' => false,
        ]);

        $cache->delete();

        $this->assertDatabaseMissing('cache', [
            'hash' => 'del12345',
        ]);
    }

    public function testHasFillableFields()
    {
        $cache = new Cache();
        $this->assertEquals(
            ['hash', 'hash_long', 'form_data', 'preserve'],
            $cache->getFillable()
        );
    }
}