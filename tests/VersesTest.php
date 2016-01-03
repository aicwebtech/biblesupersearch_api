<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Models\Bible;

class VersesTest extends TestCase
{
    /**
     * Test installation of a Bible
     */
    public function testInstall() {
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
     * Tests all installed Bibles to make sure they're properly installed
     */
    public function testInstalledBibles() {
        $Bibles = Bible::where('installed', 1)->get();
        
        foreach($Bibles as $Bible) {
            $this->assertTrue( Schema::hasTable('verses_' . $Bible->module) );
            $verses_class_static = Bible::getVerseClassNameByModule($Bible->module);
            $verses_class = $Bible->getVerseClassName();
            $this->assertInstanceOf('App\Models\Bible', $Bible);
            $this->assertEquals($verses_class_static, $verses_class, 'Static and dynamic verses classes do not match.');
            
            // Grab a few verses from the database
            $verses = $verses_class::orderBy('id', 'asc')->take(10)->get();
            $this->assertCount(10, $verses, $Bible->module . ' has empty table');
            $this->assertTrue(in_array($verses[0]->book, [1, 40]), 'Test verese did not come from Genesis or Matthew');
            $this->assertEquals(1, $verses[0]->id);
            $this->assertNotEmpty($verses[0]->text);
        }
    }
    
    /**
     * Tests all enabled Bibles to make sure they're properly installed
     */
    public function testEnabledBibles() {
        $Bibles = Bible::where('enabled', 1)->get();
        
        foreach($Bibles as $Bible) {
            // Make sure it's installed and the verses table exists
            $this->assertEquals(1, $Bible->installed);
            $this->assertTrue( Schema::hasTable('verses_' . $Bible->module) );
        }
    }
}
