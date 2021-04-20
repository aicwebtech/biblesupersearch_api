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
}
