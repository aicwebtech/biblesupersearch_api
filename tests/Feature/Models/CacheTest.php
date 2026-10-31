<?php

namespace Tests\Feature\Models;

use App\Models\Cache;
use Tests\TestCase;

class CacheTest extends TestCase
{
    /**
     * A throwaway cache row's hash pair, removed again in tearDown however the test ends.
     * Real hashes are hex, so the 'test_' prefix never matches one; test:cleanup sweeps it.
     *
     * @return array{0: string, 1: string} hash, hash_long
     */
    protected function fakeHashes(): array
    {
        $hash      = 'test_' . bin2hex(random_bytes(4));
        $hash_long = 'test_' . bin2hex(random_bytes(8));

        $this->deferCleanup(fn() => Cache::where('hash', $hash)->orWhere('hash_long', $hash_long)->delete());

        return [$hash, $hash_long];
    }

    public function testTableName()
    {
        $cache = new Cache();
        $this->assertEquals('cache', $cache->getTable());
    }
    
    public function testCreate()
    {
        [$hash, $hash_long] = $this->fakeHashes();

        $data = [
            'hash' => $hash,
            'hash_long' => $hash_long,
            'form_data' => json_encode(['foo' => 'bar']),
            'preserve' => true,
        ];

        $cache = Cache::create($data);

        $this->assertDatabaseHas('cache', [
            'hash' => $hash,
            'hash_long' => $hash_long,
            'form_data' => json_encode(['foo' => 'bar']),
            'preserve' => true,
        ]);
    }

    public function testUpdate()
    {
        [$hash, $hash_long] = $this->fakeHashes();

        $cache = Cache::create([
            'hash' => $hash,
            'hash_long' => $hash_long,
            'form_data' => json_encode(['baz' => 'qux']),
            'preserve' => false,
        ]);

        $cache->update(['preserve' => true]);

        $this->assertDatabaseHas('cache', [
            'hash' => $hash,
            'preserve' => true,
        ]);
    }

    public function testDelete()
    {
        [$hash, $hash_long] = $this->fakeHashes();

        $cache = Cache::create([
            'hash' => $hash,
            'hash_long' => $hash_long,
            'form_data' => json_encode(['delete' => 'me']),
            'preserve' => false,
        ]);

        $cache->delete();

        $this->assertDatabaseMissing('cache', [
            'hash' => $hash,
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