<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Bible;
use App\Engine;

class ParallelTest extends TestCase 
{
    public function testParallelSearch() 
    {
        $Engine = Engine::getInstance();
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

    public function testParallelSearch2() 
    {
        $Engine = Engine::getInstance();
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

    /**
     * A broad parallel search must stop at the configured ceilings: the per-Bible parallel
     * limit raises a 'result limit reached' error, and the global maximum caps the verses
     * returned for each Bible.
     */
    public function testMaxResults() 
    {
        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        // Lower the ceilings before the Engine is built. parallel_search_maximum_results is the
        // SQL LIMIT applied per Bible, so this bounds the work without altering the code path
        // under test. Laravel restores config between tests.
        config([
            'bss.parallel_search_maximum_results' => 60,
            'bss.global_maximum_results'          => 20,
        ]);

        if(config('bss.parallel_search_maximum_results') < config('bss.global_maximum_results')) {
            $this->markTestSkipped('Parallel search maximum results is less than overall maximum results, skipping');
        }

        $Engine = Engine::freshInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'God', 'whole_words' => FALSE, 'page_all' => TRUE]);

        // 'God' matches far more than 60 verses in each Bible, so both hit the parallel limit.
        $this->assertTrue($Engine->hasErrors());

        // Assert on a Bible's verse list, not on $results itself - that is keyed by module and
        // only ever holds one entry per Bible.
        $this->assertCount(config('bss.global_maximum_results'), $results['kjv']);
        $this->assertLessThanOrEqual(config('bss.global_maximum_results'), count($results['bishops']));
    }

    public function testPagination() 
    {
        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        if(config('bss.parallel_search_maximum_results') < config('bss.global_maximum_results')) {
            $this->markTestSkipped('Parallel search maximum results is overall maximum results, skipping');
        }

        $Engine = Engine::freshInstance();
        config(['bss.pagination.limit' => 30]);
        $page_limit = config('bss.pagination.limit'); // 30

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

    /**
     * Parallel searches are sliced to a single page before formatting, so the paging
     * metadata must still describe the full result set (354 unique verses), not just
     * the returned page. Guards the total/last_page computation for multi-Bible paging.
     */
    public function testParallelPaginationMetadata()
    {
        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        if(config('bss.parallel_search_maximum_results') < config('bss.global_maximum_results')) {
            $this->markTestSkipped('Parallel search maximum results is less than global maximum results; paging totals may be capped, skipping');
        }

        $Engine = Engine::freshInstance();
        config(['bss.pagination.limit' => 30]);

        $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'faith', 'whole_words' => FALSE, 'page' => 1]);
        $this->assertFalse($Engine->hasErrors());
        $paging = $Engine->getMetadata()->paging;
        // 354 unique verses across both Bibles, 30 per page => 12 pages.
        $this->assertEquals(354, $paging['total']);
        $this->assertEquals(30,  $paging['per_page']);
        $this->assertEquals(1,   $paging['current_page']);
        $this->assertEquals(12,  $paging['last_page']);
        $this->assertEquals(1,   $paging['from']);
        $this->assertEquals(30,  $paging['to']);

        // Last page carries the remaining 24 verses.
        $Engine->actionQuery(['bible' => ['kjv','bishops'], 'search' => 'faith', 'whole_words' => FALSE, 'page' => 12]);
        $this->assertFalse($Engine->hasErrors());
        $paging = $Engine->getMetadata()->paging;
        $this->assertEquals(354, $paging['total']);
        $this->assertEquals(12,  $paging['current_page']);
        $this->assertEquals(12,  $paging['last_page']);
        $this->assertEquals(331, $paging['from']);
        $this->assertEquals(354, $paging['to']);
    }
}