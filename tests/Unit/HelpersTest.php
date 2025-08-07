<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

use App\Helpers;

class HelpersTest extends TestCase
{
    public function testInstantiation() 
    {
        $Helpers = new Helpers();
        $this->assertInstanceOf('App\Helpers', $Helpers);
    }

    public function testStringLengthSortAsc() 
    {
        $raw = ['fish', 'hamburger', 'lobster', 'cat', 'chicken', 'pig'];
        $exp = ['cat', 'pig', 'fish', 'lobster', 'chicken', 'hamburger'];

        Helpers::sortStringsByLength($raw, 'ASC');
        $this->assertEquals($exp, $raw);
    }    

    public function testStringLengthSortDesc() 
    {
        $raw = ['fish', 'hamburger', 'lobster', 'cat', 'chicken', 'pig'];
        $exp = ['hamburger', 'lobster', 'chicken', 'fish', 'cat', 'pig'];

        Helpers::sortStringsByLength($raw, 'DESC');
        $this->assertEquals($exp, $raw);
    }

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

    public function testMaxUploadSize() 
    {
        $test = \Illuminate\Http\UploadedFile::getMaxFilesize();

        if(empty($test)) {
            $this->markTestSkipped();
        }

        $fmt  = Helpers::maxUploadSize();
        $raw  = Helpers::maxUploadSize(FALSE);
        $both = Helpers::maxUploadSize('both');

        $this->assertGreaterThan(0, $raw);
        $this->assertEquals($test, $raw);
        $this->assertNotEmpty($fmt);
        $this->assertIsArray($both);
        $this->assertGreaterThan(0, $both['raw']);
        $this->assertEquals($test, $both['raw']);
        $this->assertNotEmpty($both['fmt']);
    }

    #[DataProvider('sizeStringToIntDataProvider')]
    public function testSizeStringToInt(string|int $input, int $expected)
    {
        $this->assertEquals($expected, Helpers::sizeStringToInt($input));
    }

    static public function sizeStringToIntDataProvider()
    {
        return [
            ['450', 450],
            [450, 450],
            ['1k', 1024],
            ['1Gk', 1024],
            ['50k', 51200],
            ['128M', 134217728],
            ['512M', 536870912],
            ['2G', 2147483648],
            ['32G', 34359738368],
        ];
    }

    #[DataProvider('compareSizeDataProvider')]
    public function testCompareSize(string|int $a, string|int $b, int $expected)
    {
        $this->assertEquals($expected, Helpers::compareSize($a, $b));
    }

    public static function compareSizeDataProvider()
    {
        return [
            ['450', '450', 0],
            [450, 450, 0],
            ['450', 450, 0],
            [450, '450', 0],
            ['1k', '1024', 0],
            ['1G', '1024M', 0],
            ['50k', '51200', 0],
            ['128M', '134217728', 0],
            ['512M', '536870912', 0],
            ['2G', '2147483648', 0],
            ['32G', '34359738368', 0],

            ['1G', '2048M', -1],
            ['100M', '4G', -1],
            ['8k', 10240, -1],
            ['512k', '16M', -1],

            [10240, '8k', 1],
            ['16M', '512k', 1],
            ['2048M', '1G', 1],
            ['4G', '100M', 1],
        ];
    }
}
