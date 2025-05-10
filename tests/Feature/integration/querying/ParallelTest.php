<?php

//namespace Tests\Feature\integration\querying;

//use Tests\TestCase;
use App\Engine;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Models\Bible;

class ParallelTest extends TestCase {
    public function testParallelSearch() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        if(!Bible::isEnabled('tyndale')) {
            $this->markTestSkipped('Bible tyndale not installed or enabled');
        }

        $ov_max = min(
            config('bss.global_maximum_results'),
            config('bss.parallel_search_maximum_results')
        );

        if($ov_max < 341) {
            $this->markTestSkipped('Global maximum results is less than 341');
        }

        // KJV and Tyndales
        $results = $Engine->actionQuery(['bible' => ['kjv','tyndale'], 'search' => 'faith', 'whole_words' => FALSE, 'page_all' => TRUE]);

        $this->assertFalse($Engine->hasErrors());

        // 341 unique verses across both Bibles.
        $this->assertCount(341, $results['kjv']);
        $this->assertCount(286, $results['tyndale']); // However, Tyndale Bible is missing MOST of the OT: 286 vetted.
    }    

    public function testParallelSearch2() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        $ov_max = min(
            config('bss.global_maximum_results'),
            config('bss.parallel_search_maximum_results')
        );

        if($ov_max < 354) {
            $this->markTestSkipped('Global maximum results is less than 354');
        }

        // KJV and Bishops
        $results = $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'faith', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());

        // 354 unique verses across both Bibles.
        $this->assertCount(354, $results['kjv']);
        $this->assertCount(354, $results['bishops']);
    }

    public function testMaxResults() {
        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        if(config('bss.parallel_search_maximum_results') < config('bss.global_maximum_results')) {
            $this->markTestSkipped('Parallel search maximum results is overall maximum results, skipping');
        }

        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'God', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertCount(config('bss.global_maximum_results'), $results);
    }

    public function testPagination() {
        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        if(config('bss.parallel_search_maximum_results') < config('bss.global_maximum_results')) {
            $this->markTestSkipped('Parallel search maximum results is overall maximum results, skipping');
        }

        $Engine = new Engine();
        config(['bss.pagination.limit' => 30]);
        $page_limit = config('bss.pagination.limit'); // 30

        // Raw data structure check. - no longer applicable, multi Bible is no longer paginated for raw data_format
        // $results = $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'faith', 'whole_words' => FALSE, 'page' => 1, 'data_format' => 'raw']);
        // $this->assertFalse($Engine->hasErrors());

        // $this->assertCount($page_limit, $results['kjv']);
        // $this->assertCount($page_limit, $results['bishops']);

        // Passage format check
        $results = $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'faith', 'whole_words' => FALSE, 'page' => 1]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($page_limit, $results);

        $this->assertEquals(4,          $results[0]['book_id']);
        $this->assertEquals('12:7',     $results[0]['chapter_verse']);
        $this->assertEquals(19,         $results[29]['book_id']);
        $this->assertEquals('89:37',    $results[29]['chapter_verse']);

        $results = $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'faith', 'whole_words' => FALSE, 'page' => 2]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($page_limit, $results);

        $this->assertEquals(19,         $results[0]['book_id']);
        $this->assertEquals('92:2',     $results[0]['chapter_verse']);
        $this->assertEquals(24,         $results[29]['book_id']);
        $this->assertEquals('3:13',     $results[29]['chapter_verse']);

        $results = $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'faith', 'whole_words' => FALSE, 'page' => 12]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(24, $results);

        $this->assertEquals(60,         $results[0]['book_id']);
        $this->assertEquals('1:7',      $results[0]['chapter_verse']);
        $this->assertEquals(66,         $results[23]['book_id']);
        $this->assertEquals('22:6',     $results[23]['chapter_verse']);
    }
}
