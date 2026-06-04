<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

use App\Helpers;

class HelpersTest extends TestCase
{
    #[DataProvider('makeDataProvider')]
    public function testMake(string $class) 
    {
        $Object = Helpers::make($class);
        $this->assertInstanceOf($class, $Object, "Could not instantiate: {$class}");
    }

    public static function makeDataProvider()
    {
        return [
            ['App\Engine'],
            ['App\Models\Bible'],
            ['App\ImportManager'],
            ['App\InstallManager'],
            ['App\Search'],
            ['App\Passage'],
        ];
    }
}
