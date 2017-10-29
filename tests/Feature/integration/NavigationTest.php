<?php

//namespace Tests\Feature\integration;
//
//use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Engine;

class NavigationTest extends TestCase {

    public function testNavBasic() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Luke', $results[0]['navigation']['prev_book']);
        $this->assertEquals('Acts', $results[0]['navigation']['next_book']);
        $this->assertEquals('John 5', $results[0]['navigation']['prev_chapter']);
        $this->assertEquals('John 7', $results[0]['navigation']['next_chapter']);
        $this->assertEquals(NULL, $results[0]['navigation']['cur_chapter']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:1-5', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Luke', $results[0]['navigation']['prev_book']);
        $this->assertEquals('Acts', $results[0]['navigation']['next_book']);
        $this->assertEquals('John 5', $results[0]['navigation']['prev_chapter']);
        $this->assertEquals('John 7', $results[0]['navigation']['next_chapter']);
        $this->assertEquals('John 6', $results[0]['navigation']['cur_chapter']);
    }

    public function testNavMultiReferences() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 5 - 7', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);

        $this->assertEquals('Luke',   $results[0]['navigation']['prev_book']);
        $this->assertEquals('Acts',   $results[0]['navigation']['next_book']);
        $this->assertEquals('John 4', $results[0]['navigation']['prev_chapter']);
        $this->assertEquals('John 6', $results[0]['navigation']['next_chapter']);
        $this->assertEquals(NULL,     $results[0]['navigation']['cur_chapter']);
        $this->assertEquals('Luke',   $results[1]['navigation']['prev_book']);
        $this->assertEquals('Acts',   $results[1]['navigation']['next_book']);
        $this->assertEquals('John 5', $results[1]['navigation']['prev_chapter']);
        $this->assertEquals('John 7', $results[1]['navigation']['next_chapter']);
        $this->assertEquals(NULL,     $results[1]['navigation']['cur_chapter']);
        $this->assertEquals('Luke',   $results[2]['navigation']['prev_book']);
        $this->assertEquals('Acts',   $results[2]['navigation']['next_book']);
        $this->assertEquals('John 6', $results[2]['navigation']['prev_chapter']);
        $this->assertEquals('John 8', $results[2]['navigation']['next_chapter']);
        $this->assertEquals(NULL,     $results[2]['navigation']['cur_chapter']);
    }

    public function testNavBeginningGen() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Gen 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(NULL, $results[0]['navigation']['prev_book']);
        $this->assertEquals('Exodus', $results[0]['navigation']['next_book']);
        $this->assertEquals(NULL, $results[0]['navigation']['prev_chapter']);
        $this->assertEquals('Genesis 2', $results[0]['navigation']['next_chapter']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Gen 6', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(NULL, $results[0]['navigation']['prev_book']);
        $this->assertEquals('Exodus', $results[0]['navigation']['next_book']);
        $this->assertEquals('Genesis 5', $results[0]['navigation']['prev_chapter']);
        $this->assertEquals('Genesis 7', $results[0]['navigation']['next_chapter']);
    }

    public function testNavEndRev() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 22', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Jude', $results[0]['navigation']['prev_book']);
        $this->assertEquals(NULL, $results[0]['navigation']['next_book']);
        $this->assertEquals('Revelation 21', $results[0]['navigation']['prev_chapter']);
        $this->assertEquals(NULL, $results[0]['navigation']['next_chapter']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 18', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Jude', $results[0]['navigation']['prev_book']);
        $this->assertEquals(NULL, $results[0]['navigation']['next_book']);
        $this->assertEquals('Revelation 17', $results[0]['navigation']['prev_chapter']);
        $this->assertEquals('Revelation 19', $results[0]['navigation']['next_chapter']);
    }

    public function testContext() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $default_range = config('bss.context.range');
        $default_expected_total = $default_range * 2 + 1;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:33', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($default_expected_total, $results['kjv']);
        $this->assertEquals(33 - $default_range, $results['kjv'][0]->verse);
        $this->assertEquals(33 + $default_range, $results['kjv'][$default_range * 2]->verse);
    }

    public function testContextEndCondition() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $default_range = config('bss.context.range');
        $default_expected_total = $default_range + 1;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:71', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($default_expected_total, $results['kjv']);
        $this->assertEquals(71 - $default_range, $results['kjv'][0]->verse);
        $this->assertEquals(71, $results['kjv'][$default_range]->verse);
    }

    public function testContextBeginningCondition() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $default_range = config('bss.context.range');
        $default_expected_total = $default_range + 1;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($default_expected_total, $results['kjv']);
        $this->assertEquals(1, $results['kjv'][0]->verse);
        $this->assertEquals(1 + $default_range, $results['kjv'][$default_range]->verse);
    }

    public function testContextCustomRange() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:4', 'context' => TRUE, 'context_range' => 7]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(11, $results['kjv']);
        $this->assertEquals(1, $results['kjv'][0]->verse);
        $this->assertEquals(11, $results['kjv'][10]->verse);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:69', 'context' => TRUE, 'context_range' => 7]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['kjv']);
        $this->assertEquals(62, $results['kjv'][0]->verse);
        $this->assertEquals(71, $results['kjv'][9]->verse);
    }
}
