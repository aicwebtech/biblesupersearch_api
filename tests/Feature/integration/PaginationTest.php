<?php

//namespace Tests\Feature\integration;

//use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class PaginationTest extends TestCase {

    public function testSearchPageFirst() {
        $Engine = new Engine();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(config('bss.pagination.limit'), $results);

        $this->assertEquals(5, $results[0]['book_id']);
        $this->assertEquals('32:20', $results[0]['chapter_verse']);
        $this->assertEquals(42, $results[29]['book_id']);
        $this->assertEquals('18:42', $results[29]['chapter_verse']);
    }

    public function testSearchPageMiddle() {
        $Engine = new Engine();

        $_REQUEST['page'] = 3; // Because Laravel pulls from here
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'page' => 3]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(config('bss.pagination.limit'), $results);

        $this->assertEquals(45, $results[0]['book_id']);
        $this->assertEquals('4:13', $results[0]['chapter_verse']);
    }

    public function testSearchPageAll() {
        $Engine = new Engine();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(231, $results);
    }
}
