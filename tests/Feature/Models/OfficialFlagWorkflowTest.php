<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Bible;

/**
 * The `official` flag is set manually in the database and is the source of
 * truth for which directory a module file belongs in; bible:migrate-module-files
 * then moves the file to match. The provenance hardening must not interfere
 * with that.
 */
class OfficialFlagWorkflowTest extends TestCase
{
    protected function makeBibleFixture(int $official): Bible
    {
        $Bible = new Bible();
        $Bible->module   = 'offlag_' . bin2hex(random_bytes(3));
        $Bible->name     = 'Official Flag Fixture';
        $Bible->official = $official;
        $Bible->save();

        return $Bible;
    }

    protected function removeBibleFixture(?Bible $Bible): void
    {
        if($Bible) {
            $Bible->forceDelete();
        }
    }

    /**
     * The flag remains freely settable, and the module path follows it.
     */
    public function testOfficialFlagDrivesModuleDirectory(): void
    {
        $Bible = null;

        try {
            $Bible = $this->makeBibleFixture(0);

            $this->assertStringContainsString('unofficial', $Bible->getModuleFilePath());

            // Set manually, exactly as done in the database.
            $Bible->official = 1;
            $Bible->save();
            $Bible->refresh();

            $this->assertSame(1, (int) $Bible->official, 'official must stay settable');
            $this->assertStringContainsString(
                'modules',
                $Bible->getModuleFilePath(),
                'Module path must follow the flag, not the other way round'
            );
            $this->assertStringNotContainsString('unofficial', $Bible->getModuleFilePath());
        }
        finally {
            $this->removeBibleFixture($Bible);
        }
    }

    /**
     * migrateModuleFile() reports what it would do based on the flag, and the
     * validateModule() guard does not block ordinary module names.
     */
    public function testMigrateFollowsTheFlagForValidModules(): void
    {
        $Bible = null;

        try {
            $Bible = $this->makeBibleFixture(1);

            // No files exist for this fixture, so the dry run reports code 3
            // ("no module files") rather than refusing outright.
            $this->assertTrue($Bible->migrateModuleFile(TRUE));
            $this->assertSame(3, $Bible->migrate_code);
        }
        finally {
            $this->removeBibleFixture($Bible);
        }
    }

    /**
     * The guard only refuses names that would not validate at all.
     */
    public function testMigrateRefusesInvalidModuleName(): void
    {
        $Bible = new Bible();
        $Bible->module = '../../etc/passwd';

        $this->assertFalse($Bible->migrateModuleFile(TRUE));
        $this->assertFalse($Bible->deleteModuleFile());
    }
}
