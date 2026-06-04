<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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

    #[DataProvider('stripHtmlFromTextEntryDataProvider')]
    public function testStripHtmlFromTextEntry(string $input, string $expected)
    {
        $this->assertEquals($expected, Helpers::stripHtmlFromTextEntry($input));
    }

    public static function stripHtmlFromTextEntryDataProvider()
    {
        return [
            [
                'Copyright @copy; 2026 <b>Owner</b>',
                'Copyright © 2026 Owner',
            ],
            [
                'Line one<br>Line two<br />Line three',
                "Line one\nLine two\nLine three",
            ],
            [
                'Docs: <a href="https://example.com/path?q=1">Click Here</a>',
                'Docs: Click Here (https://example.com/path?q=1)',
            ],
            [
                '&lt;br&gt;Encoded break&lt;/br&gt; and &copy; entity',
                "Encoded break\n and © entity",
            ],
        ];
    }
}
