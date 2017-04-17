<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class ReferenceTest extends TestCase {
    public function testBasic() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 1', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32, $results[0]['verses_count']);

        // This should pull exact results as above, for the chapter is auto set to 1
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32, $results[0]['verses_count']);
    }

    public function testRandomChapter() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Random Chapter', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        // The shortest chapter has 2 verses
        $this->assertGreaterThanOrEqual(2, $results[0]['verses_count']);
        $this->assertNotEquals($results[0]['book_raw'], $results[0]['book_name']);
        $this->assertEquals('Random Chapter', $results[0]['book_raw']);
    }

    public function testRandomVerse() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Random Verse', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(1, $results[0]['verses_count']);
        $this->assertNotEquals($results[0]['book_raw'], $results[0]['book_name']);
        $this->assertEquals('Random Verse', $results[0]['book_raw']);
    }

    public function testIndefiniteStartRange() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev - 3:8', 'data_format' => 'raw']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(1,  $results['kjv'][0]->verse);

        $last = array_pop($results['kjv']);
        $this->assertEquals(66, $last->book);
        $this->assertEquals(3,  $last->chapter);
        $this->assertEquals(8,  $last->verse);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Matt - 2', 'data_format' => 'raw']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(40, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(1,  $results['kjv'][0]->verse);

        $last = array_pop($results['kjv']);
        $this->assertEquals(40, $last->book);
        $this->assertEquals(2,  $last->chapter);
        $this->assertEquals(23,  $last->verse);
    }

    public function testIndefiniteEndRange() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Matt 25 - ', 'data_format' => 'raw']);
        $res = $results['kjv'];

        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(40, $results['kjv'][0]->book);
        $this->assertEquals(25, $results['kjv'][0]->chapter);
        $this->assertEquals(1,  $results['kjv'][0]->verse);

        $last = array_pop($res);
        $this->assertEquals(40, $last->book);
        $this->assertEquals(28, $last->chapter);
        $this->assertEquals(20, $last->verse);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 12:2 -', 'data_format' => 'raw']);
        $res = $results['kjv'];

        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(12, $results['kjv'][0]->chapter);
        $this->assertEquals(2,  $results['kjv'][0]->verse);

        $last = array_pop($res);
        $this->assertEquals(66, $last->book);
        $this->assertEquals(22, $last->chapter);
        $this->assertEquals(21, $last->verse);
    }

    public function testBookNumber() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '19B 91:5-9', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Psalms', $results[0]['book_name']);
    }

}
