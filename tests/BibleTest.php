<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Bible;

class BibleTest extends TestCase {

    public function testBibleAndVerses() {
        $kjv = Bible::findByModule('kjv');
        $Verses = $kjv->verses();
        // The verses class exists for this one
        $this->assertTrue($Verses->classFileExists());
        $this->assertEquals('App\Models\Verses\Kjv', get_class($Verses));
    }

    public function testNonExistantBible() {
        try {
            $niv = Bible::findByModule('niv', TRUE); // Will throw an exception when not found
        } 
        catch (Exception $e) {
            $this->assertEquals('Illuminate\Database\Eloquent\ModelNotFoundException', get_class($e));
        }

        // Test auto generation of verses sub-class
        // The class file does not exist for the NIV - we don't support it!
        // However, we can force it to generate a Verses class for us. 
        $Bible = Bible::findByModule('kjv'); // Grab existing Bible
        $Bible->module = 'niv'; // Change the module.  Warning - do not save
        $Verses = $Bible->verses(TRUE); // Reload the verses instance
        $this->assertFalse($Verses->classFileExists()); // Make sure the class file exists flag is FALSE
        $this->assertInstanceOf('App\Models\Verses\Niv', $Verses);
    }

    public function testAddBible() {
        $module = 'bob_' . time();

        Bible::create([
            'module' => $module,
            'shortname' => $module,
            'name' => 'Bobs Bible Version',
            'year' => '2016',
            'lang' => 'Spanish',
            'lang_short' => 'es',
            'copyright' => 1,
        ]);

        $Bible = Bible::findByModule($module);

        $this->assertEquals(1, $Bible->copyright);
        $this->assertEquals(0, $Bible->installed);

        // Can't set enabled unless Bible is installed
        $Bible->enabled = 1;
        $this->assertEquals(0, $Bible->enabled);

        $Bible->install();
        $this->assertEquals(1, $Bible->installed);

        $Bible->enabled = 1;

        $this->assertEquals(1, $Bible->enabled);

        $this->assertTrue(Schema::hasTable('verses_' . $module));

        $Bible->uninstall();

        $this->assertEquals(0, $Bible->installed);
        $this->assertEquals(0, $Bible->enabled);
        $this->assertFalse(Schema::hasTable('verses_' . $module));
        $Bible->delete();
    }
    
    /* PUBLIC METHOD TESTS */
    public function testMethodGetVerseClassNameByModule() {
        // We don't test if the module value would make a valid class
        $class_name = Bible::getVerseClassNameByModule('kjv');
        $this->assertEquals('App\Models\Verses\Kjv', $class_name);
    }
    
    public function testMethodGetVerseClassName() {
        $kjv = Bible::findByModule('kjv');
        $class_name = $kjv->getVerseClassName();
        $this->assertEquals('App\Models\Verses\Kjv', $class_name);
        
        $Bible = Bible::where('module', '<>', 'kjv')->first();
        $class_name = $Bible->getVerseClassName();
        $module = $Bible->module;
        $this->assertEquals('App\Models\Verses\\' . ucfirst($module), $class_name);
    }

}
