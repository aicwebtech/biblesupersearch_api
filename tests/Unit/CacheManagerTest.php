<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\CacheManager;
use App\Models\Cache;
use Mockery;

class CacheManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testProcessFormDataSortsAndEncodes()
    {
        $manager = new CacheManager();
        $form_data = ['b' => 2, 'a' => 1];

        $reflection = new \ReflectionClass(CacheManager::class);
        $method = $reflection->getMethod('processFormData');

        $result = $method->invoke($manager, $form_data);
        $this->assertEquals(json_encode(['a' => 1, 'b' => 2]), $result);
    }

    public function testProcessFormDataWithParsingExcludesKeys()
    {
        $manager = new CacheManager();
        $form_data = ['a' => 1, 'page' => 2, 'b' => 3];
        $parsing = ['a' => [], 'page' => [], 'b' => []];

        $reflection = new \ReflectionClass(CacheManager::class);
        $method = $reflection->getMethod('processFormData');

        $result = $method->invoke($manager, $form_data, $parsing);
        $this->assertEquals(json_encode(['a' => 1, 'b' => 3]), $result);
    }

    public function testGenerateLongHashReturnsMd5()
    {
        $manager = new CacheManager();
        $data = '{"a":1}';
        $hash = (new \ReflectionClass($manager))->getMethod('_generateLongHash');
        $hash->setAccessible(true);
        $this->assertEquals(md5($data), $hash->invoke($manager, $data));
    }
}