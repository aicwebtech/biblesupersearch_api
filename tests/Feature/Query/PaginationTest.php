<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Bible;

class PaginationTest extends TestCase 
{

    public function testSearchPageFirst() 
    {
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

    public function testSearchPageFirstMulti() 
    {
        $Engine = new Engine();

        if(!Bible::isEnabled('tyndale')) {
            $this->markTestSkipped('Bible tyndale not installed or enabled');
        }

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

    public function testSearchPageMiddle() 
    {
        $Engine = new Engine();

        $_POST['page'] = 3; // Because Laravel pulls from here
        $response = $this->_testViaApi(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'page' => 3]);
        $results = $response['results'];

        // Still can't get this to test right - works from frontend and via API call.  Not via Engine
        // Issue is with how the 'page' variable gets through the request to Laravel's pagination
        // $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'page' => 3]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(config('bss.pagination.limit'), $results);

        $this->assertEquals(45, $results[0]['book_id']);
        $this->assertEquals('4:13', $results[0]['chapter_verse']);
    }

    public function testSearchPageAll() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(231, $results);
    }    

    public function testSearchPageLimit() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'page_limit' => 5]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(5, $results);        

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'page_limit' => 15]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(15, $results);
    }

    protected function _testViaApi($query) 
    {
        $config_cache = config('bss.public_access');
        $config_value = 1;
        $config_changed = false;

        if($config_cache != $config_value) {
            config(['bss.public_access' => $config_value]);
            $config_changed = true;
        }

        if(config('app.config_cache') && config('app.url') != config('app.url_env')) {
            $this->markTestSkipped('This test skipped when config caching is enabled');
        }

        $response = $this->json('POST', '/api/query', $query);

        if($response->status() == 429) {
            $this->markTestSkipped('429 Skipping due to rate limiting');
        }

        $response->assertStatus(200);

        if($config_changed) {
            config(['bss.public_access' => $config_cache]);
        }

        return $response;
    }
}
