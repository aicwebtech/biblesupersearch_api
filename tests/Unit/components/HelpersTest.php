<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Helpers;

class HelpersTest extends TestCase
{
    public function testInstantiation() {
        $Helpers = new Helpers();
        $this->assertInstanceOf('App\Helpers', $Helpers);
    }

    public function testStringLengthSortAsc() {
        $raw = ['fish', 'hamburger', 'lobster', 'cat', 'chicken', 'pig'];
        $exp = ['cat', 'pig', 'fish', 'lobster', 'chicken', 'hamburger'];

        Helpers::sortStringsByLength($raw, 'ASC');
        $this->assertEquals($exp, $raw);
    }    

    public function testStringLengthSortDesc() {
        $raw = ['fish', 'hamburger', 'lobster', 'cat', 'chicken', 'pig'];
        $exp = ['hamburger', 'lobster', 'chicken', 'fish', 'cat', 'pig'];

        Helpers::sortStringsByLength($raw, 'DESC');
        $this->assertEquals($exp, $raw);
    }

    public function testMake() {
        $classes = [
            'App\Engine',
            'App\Models\Bible',
            'App\ImportManager',
            'App\InstallManager',
            'App\Search',
            'App\Passage',
        ];

        foreach($classes as $c) {        
            $Object = Helpers::make($c);
            $this->assertInstanceOf($c, $Object, "Could not instantiate: {$c}");
        }
    }

    public function testMaxUploadSize() {
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

    public function testSizeStringToInt()
    {
        $this->assertEquals(450, Helpers::sizeStringToInt('450'));
        $this->assertEquals(450, Helpers::sizeStringToInt(450));
        $this->assertEquals(1024, Helpers::sizeStringToInt('1k'));
        $this->assertEquals(1024, Helpers::sizeStringToInt('1Gk'));
        $this->assertEquals(51200, Helpers::sizeStringToInt('50k'));
        $this->assertEquals(134217728, Helpers::sizeStringToInt('128M'));
        $this->assertEquals(536870912, Helpers::sizeStringToInt('512M'));
        $this->assertEquals(2147483648, Helpers::sizeStringToInt('2G'));
        $this->assertEquals(34359738368, Helpers::sizeStringToInt('32G'));
    }

    public function testCompareSize()
    {
        $this->assertEquals(0, Helpers::compareSize(1024, '1k'));
        $this->assertEquals(0, Helpers::compareSize('1G', '1024M'));
        $this->assertEquals(0, Helpers::compareSize('450', '450'));
        $this->assertEquals(0, Helpers::compareSize('450', 450));
        $this->assertEquals(0, Helpers::compareSize(450, '450'));
        $this->assertEquals(0, Helpers::compareSize(450, 450));

        $this->assertEquals(-1, Helpers::compareSize('1G', '2048M'));
        $this->assertEquals(-1, Helpers::compareSize('100M', '4G'));
        $this->assertEquals(-1, Helpers::compareSize('8k', 10240));
        $this->assertEquals(-1, Helpers::compareSize('512k', '16M'));

        $this->assertEquals(1, Helpers::compareSize(10240, '8k'));
        $this->assertEquals(1, Helpers::compareSize('16M', '512k'));
        $this->assertEquals(1, Helpers::compareSize('2048M', '1G'));
        $this->assertEquals(1, Helpers::compareSize('4G', '100M'));
    }
}
