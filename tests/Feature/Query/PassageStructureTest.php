<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Verses\VerseStandard;
use App\Models\Bible;

/**
 * Tests for the 'Passage' Structure type
 * Note: Most of these tests use the raw / minimal structure so
 * special testing for this is probably not needed.
 */

class PassageStructureTest extends TestCase 
{
    /**
     * Test basic passage lookup
     */
    public function testBasicLookup() 
    {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '  Hebrews 4:8; 1 Tim 3:1-5, 9 ', 'data_format' => 'passage']);

        $this->assertFalse($Engine->hasErrors());

        // Top level array should have 3 elements because we have 3 passages
        // 1 Tim 3:1-5, 9 is split into 1 Tim 3:1-5 and 1 Tim 3:9
        $this->assertCount(3, $results);

        // First passage should be the Hebrews one, because it was requested first, even though 1 Tim comes first
        $this->assertEquals('Hebrews', $results[0]['book_name']);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertCount(1, $results[0]['verses']['kjv'][4]);
        $this->assertEquals(4, $results[0]['verses']['kjv'][4][8]->chapter);
        $this->assertEquals(8, $results[0]['verses']['kjv'][4][8]->verse);
        $this->assertEquals(array(4 => [8]), $results[0]['verse_index']);

        // Test the 1 Tim 3:1-5 passage results
        $this->assertEquals('1 Timothy', $results[1]['book_name']);
        $this->assertFalse($results[1]['single_verse']);
        $this->assertCount(5, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(1, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(2, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(3, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(4, $results[1]['verses']['kjv'][3]);
        $this->assertArrayHasKey(5, $results[1]['verses']['kjv'][3]);
        $this->assertArrayNotHasKey(9, $results[1]['verses']['kjv'][3]); // Separated
        $this->assertArrayNotHasKey(6, $results[1]['verses']['kjv'][3]);
        $this->assertArrayNotHasKey(7, $results[1]['verses']['kjv'][3]);
        $this->assertArrayNotHasKey(8, $results[1]['verses']['kjv'][3]);
        $this->assertEquals(array(3 => [1,2,3,4,5]), $results[1]['verse_index']);

        $this->assertEquals('1 Timothy', $results[2]['book_name']);
        $this->assertTrue($results[2]['single_verse']);
        $this->assertEquals(array(3 => [9]), $results[2]['verse_index']);
    }

    public function testVerseIndex() 
    {
        if(!Bible::isEnabled('tyndale') && !Bible::isEnabled('luther')) {
            $this->markTestSkipped('Bibles needed for test: tyndale, luther');
        }

        if(Bible::isEnabled('tyndale')) {        
            // Tyndale Bible does not have a Romans 1:22 (it is part of v 23).  The verse index would be used to insure parallel passages are lined up correctly
            $expected_verse_index = array(1 => [20,21,22,23,24,25,26,27,28,29,30]);
            $Engine  = new Engine();
            $results = $Engine->actionQuery(['bible' => ['kjv', 'tyndale'], 'reference' => 'Rom 1:20-30', 'data_format' => 'passage']);
            $this->assertEquals($expected_verse_index, $results[0]['verse_index']);

            // Reverse the Bible order, results should be the same
            $results = $Engine->actionQuery(['bible' => ['tyndale', 'kjv'], 'reference' => 'Rom 1:20-30', 'data_format' => 'passage']);
            $this->assertEquals($expected_verse_index, $results[0]['verse_index']);
        }

        if(Bible::isEnabled('luther')) {
            // Luther Bible may not have a Romans 14:6.  The verse index would be used to insure parallel passages are lined up correctly
            $expected_verse_index = array(14 => [5,6,7,8,9]);
            $Engine  = new Engine();
            $results = $Engine->actionQuery(['bible' => ['kjv', 'luther'], 'reference' => 'Rom 14:5-9', 'data_format' => 'passage']);
            $this->assertEquals($expected_verse_index, $results[0]['verse_index']);

            // Reverse the Bible order, results should be the same
            $results = $Engine->actionQuery(['bible' => ['luther', 'kjv'], 'reference' => 'Rom 14:5-9', 'data_format' => 'passage']);
            $this->assertEquals($expected_verse_index, $results[0]['verse_index']);
        }
    }

    /**
     * Test looking up passages and single verses
     */
    public function testMixedLookup() 
    {
        $Engine  = new Engine();
        $bibles  = ['kjv','tr'];

        foreach($bibles as $key => $bible) {
            $Bible = Bible::findByModule($bible);

            if(!$Bible || !$Bible->enabled) {
                unset($bibles[$key]);
            }
        }

        $results = $Engine->actionQuery(['bible' => $bibles, 'reference' => 'Rom 3:23; Rom 6:23; Rom 5:8; Rom 10:9, 13', 'data_format' => 'passage']);

        $this->assertFalse($Engine->hasErrors());

        // We have 5 passages
        $this->assertCount(5, $results);

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

            // Rom 10:9
            $this->assertTrue($results[3]['single_verse']);
            $this->assertArrayHasKey(10, $results[3]['verses'][$bible]);
            $this->assertArrayHasKey(9, $results[3]['verses'][$bible][10]);

            // Rom 10:13
            $this->assertTrue($results[4]['single_verse']);
            $this->assertArrayHasKey(10, $results[4]['verses'][$bible]);
            $this->assertArrayHasKey(13, $results[4]['verses'][$bible][10]);
        }

        // These pull 2 passages each
        $refs = ['Rom 3:23; 6:23', 'Rom 3:23;6:23', 'Rom 3:23,6:23'];

        foreach($refs as $ref) {
            $results = $Engine->actionQuery(['bible' => $bibles, 'reference' => $ref, 'data_format' => 'passage']);
            $this->assertFalse($Engine->hasErrors());
            $this->assertCount(2, $results);
            $this->assertTrue($results[0]['single_verse']);
            $this->assertTrue($results[1]['single_verse']);

            foreach($bibles as $bible) {
                // Rom 3:23
                $this->assertArrayHasKey(3, $results[0]['verses'][$bible]);
                $this->assertArrayHasKey(23, $results[0]['verses'][$bible][3]);

                // Rom 6:23
                $this->assertArrayHasKey(6, $results[1]['verses'][$bible]);
                $this->assertArrayHasKey(23, $results[1]['verses'][$bible][6]);
            }
        }
    }

    /**
     * Test looking up passages across several chapters
     */
    public function testComplexPassageLookup() 
    {
        $Engine  = new Engine();
        //$Engine->debug = TRUE;
        $bibles  = ['kjv','tr'];

        foreach($bibles as $key => $bible) {
            $Bible = Bible::findByModule($bible);

            if(!$Bible || !$Bible->enabled) {
                unset($bibles[$key]);
            }
        }

        $results = $Engine->actionQuery(['bible' => $bibles, 'reference' => 'John 3:16, 23, 2:1-5; 14:30-15:2', 'data_format' => 'passage']);

        $this->assertFalse($Engine->hasErrors());

        // We have -only ONE- 5 passages here
        $this->assertCount(5, $results);
        $this->assertTrue($results[0]['single_verse']);
        $expected_indices = [2,3,14,15];  // Keys should be in this order
        $wrong_indices = [3,2,14,15];  // Keys should NOT be in this order

        foreach($bibles as $bible) {
            // These tests no longer relavant as this results in 5 passages not one
            //$this->assertEquals($expected_indices, array_keys($results[0]['verses'][$bible]));
            //$this->assertNotEquals($wrong_indices, array_keys($results[0]['verses'][$bible]));

            // John 3:16
            $this->assertArrayHasKey(3, $results[0]['verses'][$bible]);
            $this->assertArrayHasKey(16, $results[0]['verses'][$bible][3]);

            // John 3:23
            $this->assertArrayHasKey(3, $results[1]['verses'][$bible]);
            $this->assertArrayHasKey(23, $results[1]['verses'][$bible][3]);

            // John 2:1-5
            $this->assertArrayHasKey(2, $results[2]['verses'][$bible]);
            $this->assertArrayHasKey(1, $results[2]['verses'][$bible][2]);
            $this->assertArrayHasKey(2, $results[2]['verses'][$bible][2]);
            $this->assertArrayHasKey(3, $results[2]['verses'][$bible][2]);
            $this->assertArrayHasKey(4, $results[2]['verses'][$bible][2]);
            $this->assertArrayHasKey(5, $results[2]['verses'][$bible][2]);

            // John 14:30 - end
            $this->assertArrayHasKey(14, $results[3]['verses'][$bible]);
            $this->assertArrayHasKey(30, $results[3]['verses'][$bible][14]);
            $this->assertArrayHasKey(31, $results[3]['verses'][$bible][14]);

            // John 15:1-2
            $this->assertArrayHasKey(15, $results[4]['verses'][$bible]);
            $this->assertArrayHasKey(1, $results[4]['verses'][$bible][15]);
            $this->assertArrayHasKey(2, $results[4]['verses'][$bible][15]);
        }

        // Restructuring the query, we still have 5 passages here as that is how it's parsed
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'John 3:16, John 3:23, John 2:1-5; John 14:30-15:2', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(5, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertTrue($results[1]['single_verse']);
        $this->assertFalse($results[2]['single_verse']);
        $this->assertEquals(5, $results[2]['verses_count']);
        $this->assertFalse($results[3]['single_verse']);
        $this->assertEquals(2, $results[3]['verses_count']);
        $this->assertEquals(2, $results[4]['verses_count']);
    }

    public function testSingleVerseLookup() 
    {
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
     * Test with a basic search, with no passage limitation
     */
    public function testBasicSearch() 
    {
        $Engine  = new Engine();
        $Engine->setDefaultPageAll(TRUE);
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
        if(!Bible::isEnabled('tyndale')) {
            return;
        }

        $results = $Engine->actionQuery(['bible' => ['kjv','tyndale'], 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(234, $results); // Tyndale returns 3 verses that KJV doesn't

        // 1st verse - Deuteronomy 32:20
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals('Deuteronomy', $results[0]['book_name']);
        //$this->assertArrayNotHasKey('tyndale', $results[0]['verses']); // Not returned from Tyndale
        $this->assertArrayHasKey(32, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(20, $results[0]['verses']['kjv'][32]);

        // 18th verse = Mark 9:19 (only returned in Tyndale)
        $this->assertEquals('Mark', $results[17]['book_name']);
        //$this->assertArrayNotHasKey('kjv', $results[17]['verses']);
        $this->assertArrayHasKey(9, $results[17]['verses']['tyndale']);
        $this->assertArrayHasKey(19, $results[17]['verses']['tyndale'][9]);

        // Reverse the Bible search order
        $results = $Engine->actionQuery(['bible' => ['tyndale','kjv'], 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(234, $results); // Passage count should be the same as above

        // 1st verse - Deuteronomy 32:20
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals('Deuteronomy', $results[0]['book_name']);
        //$this->assertArrayNotHasKey('tyndale', $results[0]['verses']); // Not returned from Tyndale
        $this->assertArrayHasKey(32, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(20, $results[0]['verses']['kjv'][32]);

        // 18th verse = Mark 9:19 (only returned in Tyndale)
        $this->assertEquals('Mark', $results[17]['book_name']);
        //$this->assertArrayNotHasKey('kjv', $results[17]['verses']);
        $this->assertArrayHasKey(9, $results[17]['verses']['tyndale']);
        $this->assertArrayHasKey(19, $results[17]['verses']['tyndale'][9]);
    }

    /**
     * Test searching with passage limitation
     */
    public function testSearchWithPassage() 
    {
        $Engine  = new Engine();
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Rom', 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results); // -One passage- Now one passage for every verse
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);

        $this->assertArrayHasKey(1, $results[0]['verses']['kjv']);
        $this->assertArrayHasKey(5, $results[0]['verses']['kjv'][1]);
    }

    public function testIndefiniteChapterStart() 
    {
        $Engine  = new Engine();
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Gen -3', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);
        $this->assertEquals('1', $results[0]['chapter_verse']);
        $this->assertEquals('2', $results[1]['chapter_verse']);
        $this->assertEquals('3', $results[2]['chapter_verse']);

        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Genesis -2,3:4', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);
        $this->assertEquals('1', $results[0]['chapter_verse']);
        $this->assertEquals('2', $results[1]['chapter_verse']);
        $this->assertEquals('3:4', $results[2]['chapter_verse']);
    }

    public function testIndefiniteChapterEnd() 
    {
        $Engine  = new Engine();
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Ps 145 -', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(6, $results);
        $this->assertEquals('145', $results[0]['chapter_verse']);
        $this->assertEquals('147', $results[2]['chapter_verse']);
        $this->assertEquals('148', $results[3]['chapter_verse']);
        $this->assertEquals('150', $results[5]['chapter_verse']);

        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Ps 145-,3:4', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(7, $results);
        $this->assertEquals('3:4', $results[6]['chapter_verse']);
    }

    /**
     * Test searching with a book range limitation
     * Obsolete?? - Searches now return a passage for every verse returned, and do not group them
     */
    public function _testSearchWithBookRange() 
    {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Rom-Heb', 'search' => 'faith', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(14, $results); // The single book range reference is broken up into separate references by book.

        // Test the result count for each book
        $this->assertEquals(34, $results[0]['verses_count']);
        $this->assertEquals(7,  $results[1]['verses_count']);
        $this->assertEquals(6,  $results[2]['verses_count']);
        $this->assertEquals(20, $results[3]['verses_count']);
        $this->assertEquals(8,  $results[4]['verses_count']);
        $this->assertEquals(4,  $results[5]['verses_count']);
        $this->assertEquals(5,  $results[6]['verses_count']);
        $this->assertEquals(8,  $results[7]['verses_count']);
        $this->assertEquals(4,  $results[8]['verses_count']);
        $this->assertEquals(18, $results[9]['verses_count']);
        $this->assertEquals(8,  $results[10]['verses_count']);
        $this->assertEquals(5,  $results[11]['verses_count']);
        $this->assertEquals(2,  $results[12]['verses_count']);
        $this->assertEquals(31, $results[13]['verses_count']);

        // This will not pull results from every book in the range
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Gal-Heb', 'search' => 'church', 'data_format' => 'passage', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());

        // -Passages with no results are retained, so we can inform the user, if nessessary.- We exclude them now.  We have a separate validation for this
        $this->assertCount(11, $results); // Total number of books in range. 9 have results
        $this->assertEquals(1, $results[0]['verses_count']);
        $this->assertEquals(9, $results[1]['verses_count']);
        $this->assertEquals(2, $results[2]['verses_count']);
        $this->assertEquals(4, $results[3]['verses_count']);
        $this->assertEquals(1, $results[4]['verses_count']);
        $this->assertEquals(1, $results[5]['verses_count']);
        $this->assertEquals(3, $results[6]['verses_count']);
        $this->assertEquals(0, $results[7]['verses_count']);
        $this->assertEquals(0, $results[8]['verses_count']);
        $this->assertEquals(1, $results[9]['verses_count']);
        $this->assertEquals(2, $results[10]['verses_count']);
    }
}