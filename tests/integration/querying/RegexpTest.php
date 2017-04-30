<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class RegexpTest extends TestCase {
    public function testDotStar() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'tempt.*world', 'data_format' => 'raw', 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(3,  $results['kjv'][0]->chapter);
        $this->assertEquals(10, $results['kjv'][0]->verse);
    }

    public function testPlusSquareBrackets() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'ab[b]+', 'data_format' => 'raw', 'search_type' => 'regexp', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(216, $results['kjv']);
    }

    public function testCurlyBracketsAndComma() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'a[b]{2,}', 'data_format' => 'raw', 'search_type' => 'regexp', 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(216, $results['kjv']);
    }

    public function testCarrot() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '^Faith', 'data_format' => 'raw', 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testDollarSign() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'it,$', 'data_format' => 'raw', 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'it\.$', 'data_format' => 'raw', 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testParen() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'a(b){2,}', 'data_format' => 'raw', 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
    }
}
