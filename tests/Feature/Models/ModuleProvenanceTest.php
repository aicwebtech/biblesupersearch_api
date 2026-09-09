<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Bible;
use App\Importers\BibleSuperSearch;

/**
 * `official` decides which directory a module lives in and how it is treated,
 * so it must come from the filesystem rather than from info.json inside an
 * uploaded archive.
 */
class ModuleProvenanceTest extends TestCase
{
    /**
     * Official status is derived from the directory the archive is in. Only a
     * server-side provisioning step can write to the official directory.
     */
    public function testOfficialIsDerivedFromDirectory(): void
    {
        // kjv ships as an official module in this repo.
        $this->assertTrue(Bible::moduleFileIsOfficial('kjv'));

        // A module with no archive anywhere is not official.
        $this->assertFalse(Bible::moduleFileIsOfficial('no_such_module_here'));
    }

    /**
     * A malformed module name must not be turned into a filesystem path.
     */
    public function testInvalidModuleNameIsNotOfficial(): void
    {
        $this->assertFalse(Bible::moduleFileIsOfficial('../modules/kjv'));
        $this->assertFalse(Bible::moduleFileIsOfficial(''));
        $this->assertFalse(Bible::moduleFileIsOfficial('UPPER'));
    }

    /**
     * createFromModuleFile refuses a module name that would not validate,
     * rather than building a path out of it.
     */
    public function testCreateFromModuleFileRejectsInvalidModule(): void
    {
        $this->assertFalse(Bible::createFromModuleFile('../../etc/passwd'));
        $this->assertFalse(Bible::createFromModuleFile(''));
    }

    /**
     * The BibleSuperSearch importer always stores HTTP uploads in the
     * unofficial directory; it no longer lets info.json choose.
     */
    public function testUploadedArchiveAlwaysStoresAsUnofficial(): void
    {
        $Importer = new BibleSuperSearch();

        $this->assertStringEndsWith(
            'unofficial/',
            $Importer->getImportDir(),
            'HTTP uploads must not be written to the official module directory'
        );
    }

    /**
     * A module name that is a PHP reserved word cannot be persisted, because it
     * would generate "class For extends VerseStandard" and fatal.
     */
    public function testReservedWordModulesAreRejected(): void
    {
        foreach(['for', 'new', 'as', 'or', 'do'] as $module) {
            $this->assertFalse(Bible::validateModule($module), 'Should reject: ' . $module);
            $this->assertFalse(Bible::getVerseClassNameByModule($module));
        }

        // Ordinary module names still work.
        $this->assertTrue(Bible::validateModule('kjv'));
    }
}
