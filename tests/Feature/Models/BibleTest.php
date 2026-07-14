<?php

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use Tests\TestCase;
use App\Models\Bible;

class BibleTest extends TestCase 
{

    private $runInstallTest = FALSE; // installation test can slow things down

    public function testBibleAndVerses() 
    {
        $kjv = Bible::findByModule('kjv');
        $Verses = $kjv->verses();
        // The verses class exists for this one
        $this->assertTrue($Verses->classFileExists());
        $this->assertEquals('App\Models\Verses\Kjv', get_class($Verses));
    }

    public function testNonExistantBible() 
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $niv = Bible::findByModule('dne1', TRUE); // Will throw an exception when not found
    }

    /**
     * Test installation of a Bible
     */
    public function testInstall() 
    {
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
     * BSS-279: module_version must round-trip from the module file's info.json into the DB
     * (it is fillable), instead of being overwritten with the app version on install.
     */
    public function testModuleVersionRoundTripsFromModuleFile()
    {
        $module = 'bss279rt';
        $path   = Bible::getModulePath() . $module . '.zip';

        Bible::where('module', $module)->delete();
        $this->writeTestModuleZip($path, '6.1.0', $module);

        try {
            $Bible = Bible::createFromModuleFile($module);
            $this->assertNotFalse($Bible);
            // Previously null (not fillable); now loaded from info.json.
            $this->assertEquals('6.1.0', $Bible->module_version);
        }
        finally {
            Bible::where('module', $module)->delete();
            if (is_file($path)) { unlink($path); }
        }
    }

    /**
     * BSS-279: needsUpdate() must detect a newer module file pulled in from git (mtime moves),
     * ignore an unchanged file cheaply, and not false-flag on an equal-version rebuild.
     */
    public function testNeedsUpdateDetection()
    {
        $module = 'bss279det';
        $path   = Bible::getModulePath() . $module . '.zip';

        Bible::where('module', $module)->delete();
        $this->writeTestModuleZip($path, '6.0.0', $module);

        $Bible = Bible::createFromModuleFile($module);
        $Bible->installed    = 1;
        $Bible->installed_at = date('Y-m-d H:i:s', time() - 60);
        $Bible->needs_update = 0;
        $Bible->save();

        try {
            // Cheap gate: file older than install -> no update, zip not consulted.
            $this->touchModule($path, time() - 120);
            $this->assertFalse($Bible->fresh()->needsUpdate());

            // File newer but same version (e.g. fresh clone resets mtimes) -> no false positive.
            $this->writeTestModuleZip($path, '6.0.0', $module);
            $this->touchModule($path, time() + 10);
            $this->assertFalse($Bible->fresh()->needsUpdate());
            $this->assertEquals(0, $Bible->fresh()->needs_update);

            // File newer AND higher version -> update detected and flag persisted.
            $this->writeTestModuleZip($path, '6.5.0', $module);
            $this->touchModule($path, time() + 10);
            $this->assertTrue($Bible->fresh()->needsUpdate());
            $this->assertEquals(1, $Bible->fresh()->needs_update);

            // Cheap gate returns the persisted flag without re-reading the zip: version on disk
            // is equal (would compute FALSE) yet flag stays 1 because the file predates install.
            $this->writeTestModuleZip($path, '6.0.0', $module);
            $this->touchModule($path, time() - 300);
            $B = $Bible->fresh();
            $B->installed_at = date('Y-m-d H:i:s');
            $B->needs_update = 1;
            $B->save();
            $this->assertTrue($B->fresh()->needsUpdate());
        }
        finally {
            Bible::where('module', $module)->delete();
            if (is_file($path)) { unlink($path); }
        }
    }

    /**
     * BSS-279: a module file whose info.json carries no module_version must land in the DB with
     * the current app version rather than NULL, so later version comparisons have a baseline.
     */
    public function testModuleVersionDefaultsToAppVersionWhenMissing()
    {
        $module = 'bss279def';
        $path   = Bible::getModulePath() . $module . '.zip';

        Bible::where('module', $module)->delete();
        $this->writeTestModuleZip($path, NULL, $module);

        try {
            $Bible = Bible::createFromModuleFile($module);
            $this->assertNotFalse($Bible);
            $this->assertEquals(config('app.version'), $Bible->module_version);
        }
        finally {
            Bible::where('module', $module)->delete();
            if (is_file($path)) { unlink($path); }
        }
    }

    /**
     * BSS-279: syncing on-disk metadata to the DB (updateMetaInfo) makes the module current, so a
     * stale needs_update flag must be cleared -- otherwise needsUpdate()'s cheap mtime gate keeps
     * returning the stale flag without re-inspecting the zip.
     */
    public function testUpdateMetaInfoClearsStaleNeedsUpdate()
    {
        $module = 'bss279meta';
        $path   = Bible::getModulePath() . $module . '.zip';

        Bible::where('module', $module)->delete();
        $this->writeTestModuleZip($path, '6.0.0', $module);

        $Bible = Bible::createFromModuleFile($module);
        $Bible->installed    = 1;
        $Bible->installed_at = date('Y-m-d H:i:s');
        $Bible->needs_update = 1;
        $Bible->save();

        try {
            $Bible->updateMetaInfo();
            $this->assertEquals(0, $Bible->fresh()->needs_update);
        }
        finally {
            Bible::where('module', $module)->delete();
            if (is_file($path)) { unlink($path); }
        }
    }

    private function writeTestModuleZip($path, $version, $module)
    {
        if (is_file($path)) {
            unlink($path);
        }

        $info = [
            'name'           => 'BSS279 ' . $module,
            'shortname'      => strtoupper($module),
            'module'         => $module,
            'lang'           => 'English',
            'lang_short'     => 'en',
            'official'       => 1,
        ];

        if ($version !== NULL) {
            $info['module_version'] = $version;
        }

        $info = json_encode($info);

        $Zip = new \ZipArchive();
        $Zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $Zip->addFromString('info.json', $info);
        $Zip->addFromString('verses.txt', '# test');
        $Zip->close();
        clearstatcache(TRUE, $path);
    }

    private function touchModule($path, $timestamp)
    {
        touch($path, $timestamp);
        clearstatcache(TRUE, $path);
    }

    /**
     * Test installation of an Unofficial Bible module
     */
    public function testInstallUnofficial()
    {
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

        $Bible->official = 1;
        $Bible->save();
        $Bible->migrateModuleFile();

        $Bible->enabled = 1;
        $Bible->save();
    }

    public function testAddBible() 
    {
        $module = 'bobs_test_bible';
        $Bible = Bible::findByModule($module);

        if($Bible) {
            $Bible->uninstall();
            $Bible->forceDelete();
        }

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

        $Bible->install(TRUE);
        $this->assertFalse($Bible->hasErrors());
        $this->assertEquals(1, $Bible->installed);

        $Bible->enabled = 1;
        $this->assertEquals(1, $Bible->enabled);

        $class_name = $Bible->getVerseClassName();
        $this->assertEquals('App\Models\Verses\BobsTestBible', $class_name);

        $this->assertTrue(Schema::hasTable('verses_' . $module));

        $Bible->uninstall();

        $this->assertEquals(0, $Bible->installed);
        $this->assertEquals(0, $Bible->enabled);
        $this->assertFalse(Schema::hasTable('verses_' . $module));
        $Bible->forceDelete();
    }

    /* PUBLIC METHOD TESTS */
    public function testMethodGetVerseClassNameByModule() 
    {
        // We don't test if the module value would make a valid class
        $class_name = Bible::getVerseClassNameByModule('kjv');
        $this->assertEquals('App\Models\Verses\Kjv', $class_name);
    }

    public function testMethodGetVerseClassName() 
    {
        $kjv = Bible::findByModule('kjv');
        $class_name = $kjv->getVerseClassName();
        $this->assertEquals('App\Models\Verses\Kjv', $class_name);

        $Bible = Bible::where('module', '<>', 'kjv')->where('installed', '=', 1)->first();

        if($Bible) {
            $class_name = $Bible->getVerseClassName();
            $module = $Bible->module;
            $this->assertEquals('App\Models\Verses\\' . studly_case($module), $class_name);
        }
    }

    public function testBibleTable() 
    {
        // Raw queries require us to include the db prefix
        $prefix = DB::getTablePrefix();
        $bibles = DB::select(sprintf('SELECT * FROM %sbibles LIMIT 1', $prefix));

        $this->assertCount(1, $bibles);
        $this->assertInstanceOf('stdClass', $bibles[0]);

        // Generic query builder - no prefix needed
        $Bible = DB::table('bibles')->first();
        $this->assertInstanceOf('stdClass', $Bible);

        // Bible model query builder - no prefix needed
        $Bible = Bible::first();
        $this->assertInstanceOf('App\Models\Bible', $Bible);
    }

    public function testBibleMigrate() 
    {
        $kjv = Bible::findByModule('kjv');
        $of_path = Bible::getModulePath() . $kjv->getModuleFileName();
        $un_path = Bible::getUnofficialModulePath() . $kjv->getModuleFileName();

        // Attempt to migrate, it won't need it.
        $kjv->migrateModuleFile();
        $this->assertEquals($kjv->migrate_code, 0);
        $path = $kjv->getModuleFilePath(TRUE);
        $this->assertEquals($kjv->getModuleFilePath(), $of_path); // KJV is always official
        $this->assertTrue(is_file($of_path));

        // Temporarily make KJV unofficial and migrate it
        $kjv->official = 0;
        $kjv->save;
        $this->assertEquals($kjv->getModuleFilePath(), $un_path);
        $kjv->migrateModuleFile();
        $this->assertEquals($kjv->migrate_code, 2);
        $this->assertTrue(is_file($un_path));

        // Make KJV official and migrate it back
        $kjv->official = 1;
        $kjv->save;
        $this->assertEquals($kjv->getModuleFilePath(), $of_path);
        $kjv->migrateModuleFile();
        $this->assertEquals($kjv->migrate_code, 2);
        $this->assertTrue(is_file($of_path));
    }

    public function testBibleChapterVerse() 
    {
        $kjv = Bible::findByModule('kjv');

        $counts = $kjv->getChapterVerseCount();
        $this->assertCount(66, $counts);
        $this->assertEquals(150, $counts[19]['chapters']);
        $this->assertEquals(176, $counts[19]['chapter_verses'][119]);
        $this->assertEquals(2,   $counts[19]['chapter_verses'][117]);
        $this->assertEquals(28,  $counts[06]['chapter_verses'][18]);
        $this->assertEquals(27,  $counts[40]['chapter_verses'][17]); // Matt 17 - missing v 21 in Critical Text
        $this->assertEquals(20,  $counts[41]['chapter_verses'][16]); // Matt 16 - missing v 9-20 in Critical Text

        // Verbose
        $counts = $kjv->getChapterVerseCount(TRUE);

        $this->assertCount(66, $counts);
        $this->assertEquals(150, $counts[19]['chapters']);
        $this->assertEquals(150, $counts[19]['chapters_max']);
        $this->assertEquals(176, $counts[19]['chapter_verses'][119]['verses']);
        $this->assertEquals(2,   $counts[19]['chapter_verses'][117]['verses']);
        $this->assertEquals(28,  $counts[06]['chapter_verses'][ 18]['verses']);       
        $this->assertEquals(27,  $counts[40]['chapter_verses'][ 17]['verses']); // Matt 17 - missing v 21 in Critical Text
        $this->assertEquals(20,  $counts[41]['chapter_verses'][ 16]['verses']); // Matt 16 - missing v 9-20 in Critical Text 
        $this->assertEquals(176, $counts[19]['chapter_verses'][119]['verses_max']);
        $this->assertEquals(2,   $counts[19]['chapter_verses'][117]['verses_max']);
        $this->assertEquals(28,  $counts[06]['chapter_verses'][ 18]['verses_max']);
        $this->assertEquals(27,  $counts[40]['chapter_verses'][ 17]['verses_max']); // Matt 17 - missing v 21 in Critical Text
        $this->assertEquals(20,  $counts[41]['chapter_verses'][ 16]['verses_max']); // Matt 16 - missing v 9-20 in Critical Text
    }

    public function testGetBookList(): void
    {
        $kjv = Bible::findByModule('kjv');

        // Clear any previously cached value so we exercise the generation path
        Bible::where('module', 'kjv')->update(['book_list' => null]);
        $kjv->book_list = null;

        $bookList = $kjv->getBookList();
        $this->assertSame('entire', $bookList);

        // Assert persisted to DB
        $fresh = Bible::findByModule('kjv');
        $this->assertSame('entire', $fresh->book_list);

        // Assert cached path returns the same value without regenerating
        $this->assertSame('entire', $fresh->getBookList());
    }

}
