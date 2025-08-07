<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Engine;

class RegexpTest extends TestCase 
{
    public function testDotStar() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'tempt.*world', 'data_format' => 'raw', 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(3,  $results['kjv'][0]->chapter);
        $this->assertEquals(10, $results['kjv'][0]->verse);
    }

    public function testBooleanDotStar() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '`tempt.*world`', 'data_format' => 'raw', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(3,  $results['kjv'][0]->chapter);
        $this->assertEquals(10, $results['kjv'][0]->verse);
    }

    public function testPlusSquareBrackets() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'ab[b]+', 'data_format' => 'raw', 'search_type' => 'regexp', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        //$this->assertCount(216, $results['kjv']); // 218 with Psalms headers
        $this->assertCount(218, $results['kjv']); // 218 with Psalms headers
    }

    #[DataProvider('withQuotesDataProvider')]
    public function testWithQuotes(string $search, string $search_type)
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => $search, 'data_format' => 'raw', 'search_type' => $search_type, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertNotEmpty($results['kjv']);
    }

    public static function withQuotesDataProvider()
    {
        return [
            ['"created the heaven"', 'regexp'], 
            ['`created the heaven`', 'regexp'],
            ['"`created the heaven`"', 'regexp'],
            ['`"created the heaven"`', 'regexp'],
            // ['"`created the heaven`"', 'boolean'], // known issue with this query
            // ['`"created the heaven"`', 'boolean'], // known issue with this query
        ];
    }

    public function testBooleanPlusSquareBrackets() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '`ab[b]+`', 'data_format' => 'raw', 'search_type' => 'boolean', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(218, $results['kjv']);
    }

    public function testCurlyBracketsAndComma() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'a[b]{2,}', 'data_format' => 'raw', 'search_type' => 'regexp', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(218, $results['kjv']);
    }

    public function testBooleanCurlyBracketsAndComma() {

        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '`a[b]{2,}`', 'data_format' => 'raw', 'search_type' => 'boolean', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(218, $results['kjv']);
    }

    public function testCarrot() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '^Faith', 'data_format' => 'raw', 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(2, $results['kjv']);
    }

    public function testBooleanCarrot() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '`^Faith`', 'data_format' => 'raw', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(2, $results['kjv']);
    }

    #[DataProvider('dollarSignDataProvider')]
    public function testDollarSign(string $search, string $search_type) 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => $search, 'data_format' => 'raw', 'search_type' => $search_type]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertNotEmpty($results['kjv']);
    }

    static public function dollarSignDataProvider()
    {
        return [
            ['it,$', 'regexp'],
            ['it\.$' , 'regexp'],
            ['`it,$`'   , 'boolean'],
            ['`it\.$`'  , 'boolean'],
        ];
    }

    public function testParen() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'a(b){2,}', 'data_format' => 'raw', 'search_type' => 'regexp', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(218, $results['kjv']);
    }

    public function testBooleanParen() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '`a(b){2,}`', 'data_format' => 'raw', 'search_type' => 'boolean', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(218, $results['kjv']);
    }

    public function testBooleanProx() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '`tempt.*world` PROX(11) hear', 'data_format' => 'raw', 'search_type' => 'boolean', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(5, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(2,  $results['kjv'][0]->chapter);
        $this->assertEquals(29, $results['kjv'][0]->verse);
        $this->assertEquals(66, $results['kjv'][2]->book);
        $this->assertEquals(3,  $results['kjv'][2]->chapter);
        $this->assertEquals(10, $results['kjv'][2]->verse);
    }
}
