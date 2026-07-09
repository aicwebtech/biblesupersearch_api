<?php

namespace Tests\Feature\Query;
//
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Engine;

class NavigationTest extends TestCase 
{

    public function testNavBasic() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Luke', $results[0]['nav']['prev_book']);
        $this->assertEquals('Acts', $results[0]['nav']['next_book']);
        $this->assertEquals('John 5', $results[0]['nav']['prev_chapter']);
        $this->assertEquals('John 7', $results[0]['nav']['next_chapter']);
        $this->assertEquals(44, $results[0]['nav']['nb']); // Next book id
        $this->assertEquals(42, $results[0]['nav']['pb']); // Prev book id
        $this->assertEquals(43, $results[0]['nav']['pcb']); // Prev chapter book
        $this->assertEquals(5,  $results[0]['nav']['pcc']); // Prev chapter chapter
        $this->assertEquals(43, $results[0]['nav']['ncb']); // Next chapter book
        $this->assertEquals(7,  $results[0]['nav']['ncc']); // Next chapter chapter
        $this->assertEquals(NULL, $results[0]['nav']['ccb']); // Current chapter book
        $this->assertEquals(NULL, $results[0]['nav']['ccc']); // Current chapter chapter
        $this->assertEquals(NULL, $results[0]['nav']['cur_chapter']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:1-5', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Luke', $results[0]['nav']['prev_book']);
        $this->assertEquals('Acts', $results[0]['nav']['next_book']);
        $this->assertEquals('John 5', $results[0]['nav']['prev_chapter']);
        $this->assertEquals('John 7', $results[0]['nav']['next_chapter']);
        $this->assertEquals('John 6', $results[0]['nav']['cur_chapter']);
        $this->assertEquals(44, $results[0]['nav']['nb']); // Next book id
        $this->assertEquals(42, $results[0]['nav']['pb']); // Prev book id
        $this->assertEquals(43, $results[0]['nav']['pcb']); // Prev chapter book
        $this->assertEquals(5,  $results[0]['nav']['pcc']); // Prev chapter chapter
        $this->assertEquals(43, $results[0]['nav']['ncb']); // Next chapter book
        $this->assertEquals(7,  $results[0]['nav']['ncc']); // Next chapter chapter
        $this->assertEquals(43, $results[0]['nav']['ccb']); // Current chapter book
        $this->assertEquals(6,  $results[0]['nav']['ccc']); // Current chapter chapter
    }

    public function testNavMultiReferences() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 5 - 7', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);

        $this->assertEquals('Luke',   $results[0]['nav']['prev_book']);
        $this->assertEquals('Acts',   $results[0]['nav']['next_book']);
        $this->assertEquals('John 4', $results[0]['nav']['prev_chapter']);
        $this->assertEquals('John 6', $results[0]['nav']['next_chapter']);
        $this->assertEquals(NULL,     $results[0]['nav']['cur_chapter']);
        $this->assertEquals('Luke',   $results[1]['nav']['prev_book']);
        $this->assertEquals('Acts',   $results[1]['nav']['next_book']);
        $this->assertEquals('John 5', $results[1]['nav']['prev_chapter']);
        $this->assertEquals('John 7', $results[1]['nav']['next_chapter']);
        $this->assertEquals(NULL,     $results[1]['nav']['cur_chapter']);
        $this->assertEquals('Luke',   $results[2]['nav']['prev_book']);
        $this->assertEquals('Acts',   $results[2]['nav']['next_book']);
        $this->assertEquals('John 6', $results[2]['nav']['prev_chapter']);
        $this->assertEquals('John 8', $results[2]['nav']['next_chapter']);
        $this->assertEquals(NULL,     $results[2]['nav']['cur_chapter']);
    }

    public function testNavBeginningGen() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Gen 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(NULL, $results[0]['nav']['prev_book']);
        $this->assertEquals('Exodus', $results[0]['nav']['next_book']);
        $this->assertEquals(NULL, $results[0]['nav']['prev_chapter']);
        $this->assertEquals('Genesis 2', $results[0]['nav']['next_chapter']);
        $this->assertEquals(2,  $results[0]['nav']['nb']);      // Next book id
        $this->assertEquals(NULL, $results[0]['nav']['pb']);    // Prev book id
        $this->assertEquals(NULL, $results[0]['nav']['pcb']);   // Prev chapter book
        $this->assertEquals(NULL,  $results[0]['nav']['pcc']);  // Prev chapter chapter
        $this->assertEquals(1, $results[0]['nav']['ncb']);      // Next chapter book
        $this->assertEquals(2,  $results[0]['nav']['ncc']);     // Next chapter chapter
        $this->assertEquals(NULL, $results[0]['nav']['ccb']);   // Current chapter book
        $this->assertEquals(NULL, $results[0]['nav']['ccc']);   // Current chapter chapter

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Gen 6', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(NULL, $results[0]['nav']['prev_book']);
        $this->assertEquals('Exodus', $results[0]['nav']['next_book']);
        $this->assertEquals('Genesis 5', $results[0]['nav']['prev_chapter']);
        $this->assertEquals('Genesis 7', $results[0]['nav']['next_chapter']);
    }

    public function testNavEndRev() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 22', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Jude', $results[0]['nav']['prev_book']);
        $this->assertEquals(NULL, $results[0]['nav']['next_book']);
        $this->assertEquals('Revelation 21', $results[0]['nav']['prev_chapter']);
        $this->assertEquals(NULL, $results[0]['nav']['next_chapter']);
        $this->assertEquals(NULL, $results[0]['nav']['nb']);    // Next book id
        $this->assertEquals(65, $results[0]['nav']['pb']);      // Prev book id
        $this->assertEquals(66, $results[0]['nav']['pcb']);     // Prev chapter book
        $this->assertEquals(21, $results[0]['nav']['pcc']);     // Prev chapter chapter
        $this->assertEquals(NULL, $results[0]['nav']['ncb']);   // Next chapter book
        $this->assertEquals(NULL, $results[0]['nav']['ncc']);   // Next chapter chapter
        $this->assertEquals(NULL, $results[0]['nav']['ccb']);   // Current chapter book
        $this->assertEquals(NULL, $results[0]['nav']['ccc']);   // Current chapter chapter

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rev 18', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals('Jude', $results[0]['nav']['prev_book']);
        $this->assertEquals(NULL, $results[0]['nav']['next_book']);
        $this->assertEquals('Revelation 17', $results[0]['nav']['prev_chapter']);
        $this->assertEquals('Revelation 19', $results[0]['nav']['next_chapter']);
    }

    /**
     * BSS-266: prev/next book navigation must skip books that are absent from the queried Bible.
     * The 'tyndale' testing Bible contains Genesis-Deuteronomy (1-5), Jonah (32) and the full NT,
     * so navigation must jump across the 6-31 and 33-39 gaps rather than stepping +/-1.
     */
    public function testNavSkipsGapsForPartialOtBible()
    {
        $Engine = new Engine();

        // Deuteronomy (5) is the last available OT book before the gap: next jumps to Jonah (32).
        $results = $Engine->actionQuery(['bible' => 'tyndale', 'reference' => 'Deuteronomy 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(4,          $results[0]['nav']['pb']);          // Numbers
        $this->assertEquals(32,         $results[0]['nav']['nb']);          // Jonah, skipping 6-31
        $this->assertEquals('Numbers',  $results[0]['nav']['prev_book']);
        $this->assertEquals('Jonah',    $results[0]['nav']['next_book']);

        // Last chapter of Deuteronomy: next chapter must roll into Jonah 1 across the gap.
        $results = $Engine->actionQuery(['bible' => 'tyndale', 'reference' => 'Deuteronomy 34', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32,             $results[0]['nav']['ncb']);     // Jonah
        $this->assertEquals(1,              $results[0]['nav']['ncc']);
        $this->assertEquals('Deuteronomy 33', $results[0]['nav']['prev_chapter']);
        $this->assertEquals('Jonah 1',        $results[0]['nav']['next_chapter']);

        // Jonah (32) sits alone: previous jumps back to Deuteronomy (5), next forward to Matthew (40).
        $results = $Engine->actionQuery(['bible' => 'tyndale', 'reference' => 'Jonah 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(5,                 $results[0]['nav']['pb']);   // Deuteronomy
        $this->assertEquals(40,                $results[0]['nav']['nb']);   // Matthew
        $this->assertEquals('Deuteronomy',     $results[0]['nav']['prev_book']);
        $this->assertEquals('Matthew',         $results[0]['nav']['next_book']);
        $this->assertEquals('Deuteronomy 34',  $results[0]['nav']['prev_chapter']);

        // Last chapter of Jonah: next chapter rolls into Matthew 1.
        $results = $Engine->actionQuery(['bible' => 'tyndale', 'reference' => 'Jonah 4', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(40,        $results[0]['nav']['ncb']);          // Matthew
        $this->assertEquals(1,         $results[0]['nav']['ncc']);
        $this->assertEquals('Matthew 1', $results[0]['nav']['next_chapter']);

        // Matthew (40) begins the NT: previous jumps back to Jonah (32).
        $results = $Engine->actionQuery(['bible' => 'tyndale', 'reference' => 'Matthew 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32,      $results[0]['nav']['pb']);             // Jonah
        $this->assertEquals(41,      $results[0]['nav']['nb']);             // Mark
        $this->assertEquals('Jonah', $results[0]['nav']['prev_book']);
        $this->assertEquals('Mark',  $results[0]['nav']['next_book']);
    }

    /**
     * BSS-266: an NT-only Bible ('tr') must not offer navigation into the absent Old Testament.
     */
    public function testNavNtOnlyBibleHidesOldTestament()
    {
        $Engine = new Engine();

        // Matthew (40) is the first available book: no previous book/chapter.
        $results = $Engine->actionQuery(['bible' => 'tr', 'reference' => 'Matthew 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(NULL,   $results[0]['nav']['pb']);
        $this->assertEquals(NULL,   $results[0]['nav']['prev_book']);
        $this->assertEquals(NULL,   $results[0]['nav']['prev_chapter']);
        $this->assertEquals(41,     $results[0]['nav']['nb']);             // Mark
        $this->assertEquals('Mark', $results[0]['nav']['next_book']);

        // Revelation (66) is the last available book: no next book/chapter.
        $results = $Engine->actionQuery(['bible' => 'tr', 'reference' => 'Revelation 22', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(65,     $results[0]['nav']['pb']);             // Jude
        $this->assertEquals(NULL,   $results[0]['nav']['nb']);
        $this->assertEquals('Jude', $results[0]['nav']['prev_book']);
        $this->assertEquals(NULL,   $results[0]['nav']['next_book']);
    }

    /**
     * BSS-266: an OT-only Bible ('wlc') must not offer navigation into the absent New Testament.
     * Asserted on book IDs only, since 'wlc' uses Hebrew book names and versification.
     */
    public function testNavOtOnlyBibleHidesNewTestament()
    {
        $Engine = new Engine();

        // Genesis (1) is the first available book: no previous book, next is Exodus (2).
        $results = $Engine->actionQuery(['bible' => 'wlc', 'reference' => 'Genesis 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(NULL, $results[0]['nav']['pb']);
        $this->assertEquals(2,    $results[0]['nav']['nb']);

        // Malachi (39) is the last available book: no next book into the NT.
        $results = $Engine->actionQuery(['bible' => 'wlc', 'reference' => 'Malachi 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(38,   $results[0]['nav']['pb']);              // Zechariah
        $this->assertEquals(NULL, $results[0]['nav']['nb']);
    }

    /**
     * BSS-266: navigation books are the union across all queried Bibles. Querying Deuteronomy in
     * both 'tyndale' (which lacks Joshua-Malachi) and 'wlc' (full OT) fills the gap, so the next
     * book becomes Joshua (6) instead of tyndale's lone Jonah (32).
     */
    public function testNavMergesBooksAcrossBibles()
    {
        $Engine = new Engine();

        // tyndale alone: gap after Deuteronomy jumps straight to Jonah (32).
        $results = $Engine->actionQuery(['bible' => 'tyndale', 'reference' => 'Deuteronomy 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(32, $results[0]['nav']['nb']);

        // tyndale + wlc: wlc supplies Joshua (6), so the next book is now Joshua, not Jonah.
        $results = $Engine->actionQuery(['bible' => ['tyndale', 'wlc'], 'reference' => 'Deuteronomy 1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(4, $results[0]['nav']['pb']);                 // Numbers
        $this->assertEquals(6, $results[0]['nav']['nb']);                 // Joshua
    }

    public function testContext()
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $default_range = config('bss.context.range');
        $default_expected_total = $default_range * 2 + 1;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:33', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($default_expected_total, $results['kjv']);
        $this->assertEquals(33 - $default_range, $results['kjv'][0]->verse);
        $this->assertEquals(33 + $default_range, $results['kjv'][$default_range * 2]->verse);
    }

    public function testContextEndCondition() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $default_range = config('bss.context.range');
        $default_expected_total = $default_range + 1;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:71', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($default_expected_total, $results['kjv']);
        $this->assertEquals(71 - $default_range, $results['kjv'][0]->verse);
        $this->assertEquals(71, $results['kjv'][$default_range]->verse);
    }

    public function testContextBeginningCondition() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $default_range = config('bss.context.range');
        $default_expected_total = $default_range + 1;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:1', 'context' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($default_expected_total, $results['kjv']);
        $this->assertEquals(1, $results['kjv'][0]->verse);
        $this->assertEquals(1 + $default_range, $results['kjv'][$default_range]->verse);
    }

    public function testContextCustomRange() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:4', 'context' => TRUE, 'context_range' => 7]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(11, $results['kjv']);
        $this->assertEquals(1, $results['kjv'][0]->verse);
        $this->assertEquals(11, $results['kjv'][10]->verse);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 6:69', 'context' => TRUE, 'context_range' => 7]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['kjv']);
        $this->assertEquals(62, $results['kjv'][0]->verse);
        $this->assertEquals(71, $results['kjv'][9]->verse);
    }
}
