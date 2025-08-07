<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

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
        if(!Bible::isEnabled('rvg')) {
            $this->markTestSkipped('RVG Bible needed for this test');
        }

        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'rvg', 'reference' => 'Random Chapter', 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        // The shortest chapter has 2 verses
        $this->assertGreaterThanOrEqual(2, $results[0]['verses_count']);
        $this->assertNotEquals($results[0]['book_raw'], $results[0]['book_name']);
        $this->assertEquals('Random Chapter', $results[0]['book_raw']);

        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'rvg', 'reference' => 'Random Verse', 'data_format' => 'passage']);
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

    #[DataProvider('indefinteRangeDataProvider')]
    public function testIndefiniteEndRange(string $query, int $startBook, int $startChapter, int $startVerse, int $endBook, int $endChapter, int $endVerse) 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => $query, 'data_format' => 'raw']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals($startBook, $results['kjv'][0]->book);
        $this->assertEquals($startChapter, $results['kjv'][0]->chapter);
        $this->assertEquals($startVerse,  $results['kjv'][0]->verse);

        $last = array_pop($results['kjv']);
        $this->assertEquals($endBook, $last->book);
        $this->assertEquals($endChapter, $last->chapter);
        $this->assertEquals($endVerse, $last->verse);
    }

    public static function indefinteRangeDataProvider()
    {
        return [
            ['Matt 25 - ', 40, 25, 1, 40, 28, 20],
            ['Rev 12:2 -', 66, 12, 2, 66, 12, 17],
            ['Rev 12:2 - :', 66, 12, 2, 66, 22, 21],
        ];
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

    #[DataProvider('referenceAdjustmentDAtaProvider')]
    public function testReferenceAdjustment(string $query, string $expected) 
    {
        $Engine = Engine::getInstance();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => $query, 'data_format' => 'passage']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals($expected, $results[0]['chapter_verse']);
    }

    public static function referenceAdjustmentDAtaProvider()
    {
        return [
            ['Rom', '1'],
            ['Rom 16-17', '16'],
            ['Ps 91:14-20', '91:14 - 16'],
            ['Rev 12:2 -', '12:2 - 17'],
            ['Rev 21:17 -', '21:17 - 27'],
        ];
    }

    public function testReferenceAdjustmentThroughEndofBook()
    {
        // Rev 12:2 through end of BOOK
        
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 12:2 - :', 'data_format' => 'passage']);
        $this->assertEquals('12:2 - 17', $results[0]['chapter_verse']);
        $this->assertEquals('22', $results[10]['chapter_verse']);
    }

    public function testReferenceAdjustmentParallel()
    {
        if(!Bible::isEnabled('tyndale')) {
            $this->markTestSkipped('Tyndale Bible needed for this test');
        }

        $Engine = new Engine();
        
        // Tyndale doesn't have a vs 27, or does it?  Apparently, some editions do. So, omitting this test ...
        // $results = $Engine->actionQuery(['bible' => ['tyndale'], 'reference' => 'Rev 21:17 -', 'data_format' => 'passage']);
        // $this->assertEquals('21:17 - 27', $results[0]['chapter_verse']);

        $results = $Engine->actionQuery(['bible' => ['kjv','tyndale'], 'reference' => 'Rev 21:17 -', 'data_format' => 'passage']);
        $this->assertEquals('21:17 - 27', $results[0]['chapter_verse']);
    }
}
