<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Verses\VerseStandard;

/**
 * Tests for the 'Passage' Structure type
 * Note: Most of these tests use the raw / minimal structure so 
 * special testing for this is probably not needed.
 */

class PassageStructureTest extends TestCase {
    public function testBasicLookup() {
        $Engine = new Engine();
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '  Hebrews 4:8; 1 Tim 3:1-5, 9 ', 'data_format' => 'passage']);
        
        $this->assertFalse($Engine->hasErrors());
        
        // Top level array should have 2 elements because we have w passages
        $this->assertCount(2, $results);
        
        // First passage should be the Hebrews one, because it was requested first, even though 1 Tim comes first
        $this->assertEquals('Hebrews', $results[0]['book_name']);
        $this->assertCount(1, $results[0]['verses'][4]);
        
        print_r($results[0]['verses']);
        
        $this->assertEquals(4, $results[0]['verses'][4][8]->chapter);
        $this->assertEquals(8, $results[0]['verses'][4][8]->verse);
        
        $this->assertEquals('1 Timothy', $results[1]['book_name']);
        $this->assertCount(6, $results[1]['verses'][3]);
        
        //var_dump($results[0]['verses']);
        //var_dump($results[1]['verses']);
    }
    
}