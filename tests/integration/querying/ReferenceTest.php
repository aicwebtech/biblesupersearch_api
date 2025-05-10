<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Bible;

class ReferenceTest extends TestCase 
{

    public function testBasic() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 1', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32, $results[0]['verses_count']);

        // This should pull exact results as above, for the chapter is auto set to 1
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32, $results[0]['verses_count']);
    }

    public function testRandomChapter() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Random Chapter', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        // The shortest chapter has 2 verses
        $this->assertGreaterThanOrEqual(2, $results[0]['verses_count']);
        $this->assertNotEquals($results[0]['book_raw'], $results[0]['book_name']);
        $this->assertEquals('Random Chapter', $results[0]['book_raw']);
    }

    public function testRandomVerse() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Random Verse', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(1, $results[0]['verses_count']);
        $this->assertNotEquals($results[0]['book_raw'], $results[0]['book_name']);
        $this->assertEquals('Random Verse', $results[0]['book_raw']);
    }

    public function testRandomForeign() 
    {
        if(!Bible::isEnabled('lith')) {
            $this->assertTrue(TRUE);
            return;
        }

        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'lith', 'reference' => 'Random Chapter', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        // The shortest chapter has 2 verses
        $this->assertGreaterThanOrEqual(2, $results[0]['verses_count']);
        $this->assertNotEquals($results[0]['book_raw'], $results[0]['book_name']);
        $this->assertEquals('Random Chapter', $results[0]['book_raw']);

        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'lith', 'reference' => 'Random Verse', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(1, $results[0]['verses_count']);
        $this->assertNotEquals($results[0]['book_raw'], $results[0]['book_name']);
        $this->assertEquals('Random Verse', $results[0]['book_raw']);
    }

    public function testIndefiniteStartRange() 
    {
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

    public function testIndefiniteEndRange() 
    {
        $Engine = new Engine();
        // Matthew chapter 25 through end of book
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

        // Rev 12:2 through end of CHAPTER
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 12:2 -', 'data_format' => 'raw']);
        $res = $results['kjv'];

        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(12, $results['kjv'][0]->chapter);
        $this->assertEquals(2,  $results['kjv'][0]->verse);

        $last = array_pop($res);
        $this->assertEquals(66, $last->book);
        $this->assertEquals(12, $last->chapter);
        $this->assertEquals(17, $last->verse);

        // Rev 12:2 through end of BOOK
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 12:2 - :', 'data_format' => 'raw']);
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

    public function testDuplicatePassage() 
    {
        // Acts 5:29 is requested TWICE in the same request

        // Raw format - should return the 3 unique verses
        $Engine = Engine::getInstance();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Acts 5:29; Acts 5:27-29', 'data_format' => 'raw']);
        $this->assertFalse($Engine->hasErrors());
        
        // Count should be 3 because we only pull it once
        $this->assertCount(3, $results['kjv']); 

        // Passage format - should return 2 passages, the first containing Acts 5:29, the second containing Acts 5:27-29
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Acts 5:29; Acts 5:27-29', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(2, $results);
        $this->assertEquals('5:29', $results[0]['chapter_verse']);
        $this->assertCount(1, $results[0]['verses']['kjv'][5]);
        $this->assertEquals('5:27 - 29', $results[1]['chapter_verse']);
        $this->assertCount(3, $results[1]['verses']['kjv'][5]);
    }

    public function testBookNumber() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '19B 91:5-9', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Psalms', $results[0]['book_name']);
    }

    public function testReferenceAdjustment() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('1', $results[0]['chapter_verse']);

        // Romans only has 16 chapters
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 16-17', 'data_format' => 'passage']);
        $this->assertCount(1, $results);
        $this->assertEquals('16', $results[0]['chapter_verse']);

        // Ps 91 has 16 verses
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Ps 91:14-20', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('91:14 - 16', $results[0]['chapter_verse']);

        // Rev 12:2 through end of CHAPTER
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 12:2 -', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('12:2 - 17', $results[0]['chapter_verse']);

        // Rev 12:2 through end of BOOK
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 12:2 - :', 'data_format' => 'passage']);
        $this->assertEquals('12:2 - 17', $results[0]['chapter_verse']);
        $this->assertEquals('22', $results[10]['chapter_verse']);

        $results = $Engine->actionQuery(['bible' => ['kjv'], 'reference' => 'Rev 21:17 -', 'data_format' => 'passage']);
        $this->assertEquals('21:17 - 27', $results[0]['chapter_verse']);

        if(!Bible::isEnabled('tyndale')) {
            return;
        }

        // Tyndale doesn't have a vs 27, or does it?  Apparently, some editions do. So, omitting this test ...
        // $results = $Engine->actionQuery(['bible' => ['tyndale'], 'reference' => 'Rev 21:17 -', 'data_format' => 'passage']);
        // $this->assertEquals('21:17 - 27', $results[0]['chapter_verse']);

        $results = $Engine->actionQuery(['bible' => ['kjv','tyndale'], 'reference' => 'Rev 21:17 -', 'data_format' => 'passage']);
        $this->assertEquals('21:17 - 27', $results[0]['chapter_verse']);
    }
}
