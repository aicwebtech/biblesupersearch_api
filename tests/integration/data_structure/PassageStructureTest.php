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
    /**
     * Test basic passage lookup
     */
    public function testBasicLookup() {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '  Hebrews 4:8; 1 Tim 3:1-5, 9 ', 'data_format' => 'passage']);
        
        $this->assertFalse($Engine->hasErrors());
        
        // Top level array should have 2 elements because we have 2 passages
        $this->assertCount(2, $results);
        
        // First passage should be the Hebrews one, because it was requested first, even though 1 Tim comes first
        $this->assertEquals('Hebrews', $results[0]['book_name']);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertCount(1, $results[0]['verses']['kjv'][4]);
        $this->assertEquals(4, $results[0]['verses']['kjv'][4][8]->chapter);
        $this->assertEquals(8, $results[0]['verses']['kjv'][4][8]->verse);
        
        // Test the 1 Tim passage results
        $this->assertEquals('1 Timothy', $results[1]['book_name']);
        $this->assertFalse($results[1]['single_verse']);
        $this->assertCount(6, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(1, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(2, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(3, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(4, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(5, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(9, $results[1]['verses']['kjv'][3]);
        $this->assertArrayNotHasKey(6, $results[1]['verses']['kjv'][3]);
        $this->assertArrayNotHasKey(7, $results[1]['verses']['kjv'][3]);
        $this->assertArrayNotHasKey(8, $results[1]['verses']['kjv'][3]);
    }
    
    /** 
     * Test looking up passages and single verses
     */
    public function testMixedLookup() {
        $Engine  = new Engine();
        $bibles  = ['kjv','tr'];
        $results = $Engine->actionQuery(['bible' => $bibles, 'reference' => 'Rom 3:23; Rom 6:23; Rom 5:8; Rom 10:9, 13', 'data_format' => 'passage']);
        
        $this->assertFalse($Engine->hasErrors());
        
        // We have 4 passages
        $this->assertCount(4, $results);
        
        // All the passages are from Romans
        foreach($results as $passage) {
            $this->assertEquals('Romans', $passage['book_name']);
        }
        
        // Test each individual passage in each Bible
        foreach($bibles as $bible) {            
            // Rom 3:23
            $this->assertTrue($results[0]['single_verse']);
            $this->assertArrayHasKey(3, $results[0]['verses'][$bible]);
            $this->assertArrayHasKey(23, $results[0]['verses'][$bible][3]);
            
            // Rom 6:23
            $this->assertTrue($results[1]['single_verse']);
            $this->assertArrayHasKey(6, $results[1]['verses'][$bible]);
            $this->assertArrayHasKey(23, $results[1]['verses'][$bible][6]);
            
            // Rom 5:8
            $this->assertTrue($results[2]['single_verse']);
            $this->assertArrayHasKey(5, $results[2]['verses'][$bible]);
            $this->assertArrayHasKey(8, $results[2]['verses'][$bible][5]);
            
            // Rom 10:9, 13
            $this->assertFalse($results[3]['single_verse']);
            $this->assertArrayHasKey(10, $results[3]['verses'][$bible]);
            $this->assertArrayHasKey(9, $results[3]['verses'][$bible][10]);
            $this->assertArrayHasKey(13, $results[3]['verses'][$bible][10]);
        }
        
        // These only pull ONE passage each
        $refs = ['Rom 3:23; 6:23', 'Rom 3:23;6:23', 'Rom 3:23,6:23'];
        
        foreach($refs as $ref) {            
            $results = $Engine->actionQuery(['bible' => $bibles, 'reference' => $ref, 'data_format' => 'passage']);
            $this->assertFalse($Engine->hasErrors());
            $this->assertCount(1, $results);
            $this->assertFalse($results[0]['single_verse']);
            
            foreach($bibles as $bible) {            
                // Rom 3:23
                $this->assertArrayHasKey(3, $results[0]['verses'][$bible]);
                $this->assertArrayHasKey(23, $results[0]['verses'][$bible][3]);

                // Rom 6:23
                $this->assertArrayHasKey(6, $results[0]['verses'][$bible]);
                $this->assertArrayHasKey(23, $results[0]['verses'][$bible][6]);
            }
        }
    }
    
    /**
     * Test looking up passages across several chapters
     */
    public function testComplexPassageLookup() {
        $Engine  = new Engine();
        $bibles  = ['kjv','tr'];
        $results = $Engine->actionQuery(['bible' => $bibles, 'reference' => 'John 3:16, 23, 2:1-5; 14:30-15:2', 'data_format' => 'passage']);
        
        $this->assertFalse($Engine->hasErrors());
        
        // We have only ONE passage here
        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['single_verse']);
        $expected_indices = [2,3,14,15];  // Keys should be in this order
        $wrong_indices = [3,2,14,15];  // Keys should be in this order
        
        foreach($bibles as $bible) {            
            $this->assertEquals($expected_indices, array_keys($results[0]['verses'][$bible]));
            $this->assertNotEquals($wrong_indices, array_keys($results[0]['verses'][$bible]));

            // John 3:16
            $this->assertArrayHasKey(3, $results[0]['verses'][$bible]);
            $this->assertArrayHasKey(16, $results[0]['verses'][$bible][3]);

            // John 3:23
            $this->assertArrayHasKey(3, $results[0]['verses'][$bible]);
            $this->assertArrayHasKey(23, $results[0]['verses'][$bible][3]);
            
            // John 2:1-5
            $this->assertArrayHasKey(2, $results[0]['verses'][$bible]);
            $this->assertArrayHasKey(1, $results[0]['verses'][$bible][2]);
            $this->assertArrayHasKey(2, $results[0]['verses'][$bible][2]);
            $this->assertArrayHasKey(3, $results[0]['verses'][$bible][2]);
            $this->assertArrayHasKey(4, $results[0]['verses'][$bible][2]);
            $this->assertArrayHasKey(5, $results[0]['verses'][$bible][2]);
            
            // John 14:30 - 15:2
            $this->assertArrayHasKey(14, $results[0]['verses'][$bible]);
            $this->assertArrayHasKey(15, $results[0]['verses'][$bible]);
            $this->assertArrayHasKey(30, $results[0]['verses'][$bible][14]);
            $this->assertArrayHasKey(31, $results[0]['verses'][$bible][14]);
            $this->assertArrayHasKey(1, $results[0]['verses'][$bible][15]);
            $this->assertArrayHasKey(2, $results[0]['verses'][$bible][15]);
        }
        
        // Restructuring the query, we now have four passages here
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'John 3:16, John 3:23, John 2:1-5; John 14:30-15:2', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(4, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertTrue($results[1]['single_verse']);
        $this->assertFalse($results[2]['single_verse']);
        $this->assertFalse($results[3]['single_verse']);
    }
    
    public function testSingleVerseLookup() {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 3:16, Ps 23:1, 1 John 2:1; Acts 2:38', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(4, $results);
        
        // Jn 3:16
        $this->assertTrue($results[0]['single_verse']);
        $this->assertArrayHasKey(3, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(16, $results[0]['verses']['kjv'][3]);
        
        // Ps 23:1
        $this->assertTrue($results[1]['single_verse']);
        $this->assertArrayHasKey(23, $results[1]['verses']['kjv']);
        $this->assertArrayHasKey(1, $results[1]['verses']['kjv'][23]);
        
        // 1 Jn 2:1
        $this->assertTrue($results[2]['single_verse']);
        $this->assertArrayHasKey(2, $results[2]['verses']['kjv']);
        $this->assertArrayHasKey(1, $results[2]['verses']['kjv'][2]);
        
        // Acts 2:38
        $this->assertTrue($results[3]['single_verse']);
        $this->assertArrayHasKey(2, $results[3]['verses']['kjv']);
        $this->assertArrayHasKey(38, $results[3]['verses']['kjv'][2]);
    }
    
    /**
     * Test attempting to look up a verse that does not exist
     */
    public function testAbsentVerseLookup() {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 3:96', 'data_format' => 'passage']);
        $this->assertTrue($Engine->hasErrors());
        //$this->assertCount(0, $results);
        
        //print_r($results);
    }
    
    /**
     * Test with a basic search, with no passage limitation
     */
    public function testBasicSearch() {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'supplication', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(37, $results);
        
        // Check data structure
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals('1 Samuel', $results[0]['book_name']);
        $this->assertArrayHasKey(13, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(12, $results[0]['verses']['kjv'][13]);
        
        // Search for faith
        $results = $Engine->actionQuery(['bible' => ['kjv'], 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(231, $results);
        
        // 1st verse - Deuteronomy 32:20
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals('Deuteronomy', $results[0]['book_name']);
        $this->assertArrayHasKey(32, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(20, $results[0]['verses']['kjv'][32]);
        
        // 18th verse = Mark 10:52
        $this->assertEquals('Mark', $results[17]['book_name']);
        $this->assertArrayHasKey(10, $results[17]['verses']['kjv']);
        $this->assertArrayHasKey(52, $results[17]['verses']['kjv'][10]);
        
        // Add a second Bible (Tyndale Bible will produce less results because it lacks most OT books)
        $results = $Engine->actionQuery(['bible' => ['kjv','tyndale'], 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(234, $results); // Tyndale returns 3 verses that KJV doesn't
        
        // 1st verse - Deuteronomy 32:20
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals('Deuteronomy', $results[0]['book_name']);
        $this->assertArrayNotHasKey('tyndale', $results[0]['verses']); // Not returned from Tyndale
        $this->assertArrayHasKey(32, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(20, $results[0]['verses']['kjv'][32]);
        
        // 18th verse = Mark 9:19 (only returned in Tyndale)
        $this->assertEquals('Mark', $results[17]['book_name']);
        $this->assertArrayNotHasKey('kjv', $results[17]['verses']);
        $this->assertArrayHasKey(9, $results[17]['verses']['tyndale']);
        $this->assertArrayHasKey(19, $results[17]['verses']['tyndale'][9]);
        
        // Reverse the Bible search order
        $results = $Engine->actionQuery(['bible' => ['tyndale','kjv'], 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(234, $results); // Passage count should be the same as above
        
        // 1st verse - Deuteronomy 32:20
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals('Deuteronomy', $results[0]['book_name']);
        $this->assertArrayNotHasKey('tyndale', $results[0]['verses']); // Not returned from Tyndale
        $this->assertArrayHasKey(32, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(20, $results[0]['verses']['kjv'][32]);
        
        // 18th verse = Mark 9:19 (only returned in Tyndale)
        $this->assertEquals('Mark', $results[17]['book_name']);
        $this->assertArrayNotHasKey('kjv', $results[17]['verses']);
        $this->assertArrayHasKey(9, $results[17]['verses']['tyndale']);
        $this->assertArrayHasKey(19, $results[17]['verses']['tyndale'][9]);
    }
    
    /**
     * Test searching with passage limitation
     */
    public function testSearchWithPassage() {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Rom', 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results);
    }
    
    /**
     * Test searching with a book range limitation
     */
    public function testSearchWithBookRange() {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Rom-Heb', 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(160, $results);
    }
}