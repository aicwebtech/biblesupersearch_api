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

    public function testBookNumber() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '19B 91:5-9', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Psalms', $results[0]['book_name']);
    }
}
