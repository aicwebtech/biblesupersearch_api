<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Models\Bible;
use App\Passage;
use App\Search;

class VersesTest extends TestCase
{
    // installation test can slow things down - should be TRUE in production
    public $runInstallTest = FALSE;

    public function testLookupQuery() {
        $Bible = Bible::findByModule('kjv');
        $Passages = Passage::parseReferences('Rom 1:1-10');
        $Verses = $Bible->getSearch($Passages);
        //$Verses = $Verses_Collection->all();

        $this->assertCount(10, $Verses);
        //$this->assertContainsOnlyInstancesOf('App\Models\Verses\Kjv', $Verses);

        $this->assertEquals(45, $Verses[0]->book);
        $this->assertEquals(1, $Verses[0]->chapter);

        for($i = 1; $i <= 10; $i++) {
            $this->assertEquals($i, $Verses[$i - 1]->verse);
        }

        $Passages = Passage::parseReferences('Matt. 1:1');
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(1, $VC);
        $this->assertEquals(40, $VC[0]->book);
        $this->assertEquals(1, $VC[0]->chapter);
        $this->assertEquals(1, $VC[0]->verse);

        $Passages = Passage::parseReferences('Mark 6:4,4:2');
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(2, $VC);
        $this->assertEquals(41, $VC[0]->book);
        // The output should be in the Scriptural order, even though the references aren't
        $this->assertEquals(6,  $VC[1]->chapter);
        $this->assertEquals(4,  $VC[1]->verse);

        $Passages = Passage::parseReferences('Ps 111:8-113:2');
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(15, $VC);
        $this->assertEquals(112, $VC[3]->chapter);
        $this->assertEquals(1, $VC[3]->verse);

        $Passages = Passage::parseReferences('Ps 111');
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(10, $VC);

        $Passages = Passage::parseReferences('Ps 110-112');
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(27, $VC);

        // Todo - indefinite ranges aren't working!!!
        $Passages = Passage::parseReferences('Ps 110-112:3');
        $expected_parse = array( array('cst' => 110, 'vst' => NULL, 'cen' => 112, 'ven' => 3, 'type' => 'range') );
        $this->assertEquals($expected_parse, $Passages[0]->chapter_verse_parsed);
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(20, $VC);

        $Passages = Passage::parseReferences('Ps 110:6-112:');
        $expected_parse = array( array('cst' => 110, 'vst' => 6, 'cen' => 112, 'ven' => NULL, 'type' => 'range') );
        $this->assertEquals($expected_parse, $Passages[0]->chapter_verse_parsed);
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(22, $VC);

        // This query tells it to get vs 6 - 112 of Ch 110, not 110:6 through chapter 112
        // Only returns 2 verses
        $Passages = Passage::parseReferences('Ps 110:6-112');
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(2, $VC);

        $Passages = Passage::parseReferences('Ps 12:6-7,Rom 3:5-9');
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(7, $VC);

        // Implicit and explicit chapters
        $expected_parse = array( array('c' => 1, 'v' => NULL, 'type' => 'single') );
        $Passages = Passage::parseReferences('Jn 1');
        $this->assertEquals($expected_parse, $Passages[0]->chapter_verse_parsed);
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(51, $VC);
        $Passages = Passage::parseReferences('Jn'); // Implied chapter 1
        $this->assertEquals($expected_parse, $Passages[0]->chapter_verse_parsed);
        $VC = $Bible->getSearch($Passages);
        $this->assertCount(51, $VC);


    }

    /**
     * Test installation of a Bible
     */
    public function testInstall() {
        if(!$this->runInstallTest) {
            $this->assertTrue(TRUE);
            return;
        }

        echo(PHP_EOL . 'Installation test - offiical module' . PHP_EOL);

        $Bible = Bible::findByModule('kjv');
        $Bible->uninstall();
        $this->assertEquals(0, $Bible->installed);
        $Bible->install();
        $this->assertEquals(1, $Bible->installed);
        $this->assertTrue( Schema::hasTable('verses_kjv') );
        $Bible->enabled = 1;
        $Bible->save();
    }

    /**
     * Test installation of an Unofficial Bible module
     */
    public function testInstallUnofficial() {
       if(!$this->runInstallTest) {
            $this->assertTrue(TRUE);
            return;
        }

        echo(PHP_EOL . 'Installation test - UNoffiical module' . PHP_EOL);

        $Bible = Bible::findByModule('kjv');
        $Bible->uninstall();
        $Bible->official = 0;
        $Bible->save();
        $Bible->migrateModuleFile();

        $this->assertEquals(0, $Bible->installed);

        $Bible->install();
        $this->assertEquals(1, $Bible->installed);
        $this->assertTrue( Schema::hasTable('verses_kjv') );
        $this->testLookupQuery();

//        $Bible->uninstall();
        $Bible->official = 1;
        $Bible->save();
        $Bible->migrateModuleFile();

//        $Bible->install();
        $Bible->enabled = 1;
        $Bible->save();
    }

    /**
     * Tests all installed Bibles to make sure they're properly installed
     */
    public function testInstalledBibles() {
        $Bibles = Bible::where('installed', 1)->get();

        foreach($Bibles as $Bible) {
            if(strpos($Bible->module, 'test_bible') !== FALSE) {
                continue;
            }

            $Verses = $Bible->verses();
            $this->assertTrue( Schema::hasTable($Verses->getTable()), 'No table for module: ' . $Bible->module . ', table:' . $Verses->getTable() );
            $verses_class_static = Bible::getVerseClassNameByModule($Bible->module);
            $verses_class = $Bible->getVerseClassName();
            $this->assertInstanceOf('App\Models\Bible', $Bible);
            $this->assertEquals($verses_class_static, $verses_class, 'Static and dynamic verses classes do not match.');

            // Grab a few verses from the database
            $verses = $Verses->orderBy('id', 'asc')->take(10)->get();
            $this->assertCount(10, $verses, $Bible->module . ' has empty table');
            $this->assertGreaterThanOrEqual(1, $verses[0]->book);
            $this->assertLessThanOrEqual(66, $verses[0]->book);
            //$this->assertTrue(in_array($verses[0]->book, [1, 40]), $Bible->module . ': Test verese did not come from Genesis or Matthew');
            $this->assertEquals(1, $verses[0]->id, $Bible->module . ' verses are misnumbered');
            $this->assertNotEmpty($verses[0]->text);
        }
    }

    /**
     * Tests all enabled Bibles to make sure they're properly installed
     */
    public function testEnabledBibles() {
        $Bibles = Bible::where('enabled', 1)->get();

        foreach($Bibles as $Bible) {
            if(strpos($Bible->module, 'test_bible') !== FALSE) {
                continue;
            }

            // Make sure it's installed and the verses table exists
            $Verses = $Bible->verses();
            $this->assertEquals(1, $Bible->installed, $Bible->module . ' is enabled but NOT installed.');
            $this->assertTrue( Schema::hasTable($Verses->getTable()), 'No table for module: ' . $Bible->module . ', table:' . $Verses->getTable() );
        }
    }
}
