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
        $metadata = $Engine->getMetadata();
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(config('bss.pagination.limit'), $results);

        $this->assertEquals(5, $results[0]['book_id']);
        $this->assertEquals('32:20', $results[0]['chapter_verse']);
        $this->assertEquals(42, $results[29]['book_id']);
        $this->assertEquals('18:42', $results[29]['chapter_verse']);

        $total_pages = ceil(231 / config('bss.pagination.limit'));
        $this->assertEquals(1, $metadata->paging['current_page']);
        $this->assertEquals($total_pages, $metadata->paging['last_page']);
        $this->assertEquals(config('bss.pagination.limit'), $metadata->paging['per_page']);
    }

    public function testSearchPageFirstMulti() {
        $Engine = new Engine();

        $results = $Engine->actionQuery(['bible' => ['kjv','tyndale'], 'search' => 'faith', 'whole_words' => TRUE, 'page' => 1]);
        $metadata = $Engine->getMetadata();
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(config('bss.pagination.limit'), $results);

        $this->assertEquals(5, $results[0]['book_id']);
        $this->assertEquals('32:20', $results[0]['chapter_verse']);
        $this->assertEquals(42, $results[29]['book_id']);
        $this->assertEquals('17:19', $results[29]['chapter_verse']);

        $this->assertEquals(1, $metadata->paging['current_page']);
    }

    // Still can't get this to test right - works from frontend
    // Issue is with how the 'page' variable gets through the request to Laravel's pagination
    public function _testSearchPageMiddle() {
        $Engine = new Engine();

        $_POST['page'] = 3; // Because Laravel pulls from here
        $results = $this->_testViaApi(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'page' => 3]);
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

    protected function _testViaApi($query) {
        var_dump($query);

        $response = $this->json('GET', '/api/query', $query);
        $response->assertStatus(200);

        var_dump($response);
        die();
    }
}
