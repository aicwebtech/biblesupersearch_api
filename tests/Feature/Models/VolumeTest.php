<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Volume;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class VolumeTest extends TestCase
{
    /**
     * Every NOT NULL column on `volumes` without a default is set explicitly: type, name,
     * shortname, module and language.  See OfficialFlagWorkflowTest::makeBibleFixture().
     */
    protected function makeVolumeFixture(string $type = 'strongs', ?string $module = NULL): Volume
    {
        $suffix = bin2hex(random_bytes(3));

        $Volume = new Volume();
        $Volume->type      = $type;
        $Volume->module    = $module ?: 'vol_fixture_' . $suffix;
        $Volume->name      = 'Volume Fixture ' . $suffix;
        $Volume->shortname = 'VolFix ' . $suffix;
        $Volume->language  = 'en';
        $Volume->save();

        return $Volume;
    }

    protected function removeVolumeFixture(?Volume $Volume): void
    {
        $Volume && $Volume->forceDelete();
    }

    public function testInstallAndUninstallSetFlags(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();

            $this->assertTrue($Volume->install());
            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->installed);
            $this->assertSame(0, (int) $Volume->enabled);
            $this->assertNotNull($Volume->installed_at);

            $this->assertFalse($Volume->install());
            $this->assertContains('Already installed', $Volume->getErrors());

            $Volume->enable();
            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->enabled);

            $this->assertTrue($Volume->uninstall());
            $Volume->refresh();
            $this->assertSame(0, (int) $Volume->installed);
            $this->assertSame(0, (int) $Volume->enabled);
            $this->assertNull($Volume->installed_at);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testInstallCanEnable(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $Volume->install(FALSE, TRUE);
            $Volume->refresh();

            $this->assertSame(1, (int) $Volume->enabled);

            $Volume->disable();
            $Volume->refresh();

            $this->assertSame(0, (int) $Volume->enabled);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testCannotEnableWhileUninstalled(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $Volume->enable();
            $Volume->refresh();

            $this->assertSame(0, (int) $Volume->enabled);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testFindByModule(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();

            $this->assertSame($Volume->id, Volume::findByTypeAndModule('strongs', $Volume->module)?->id);
            $this->assertNull(Volume::findByTypeAndModule('commentary', $Volume->module));
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testModuleIsUniquePerType(): void
    {
        $Volume = $Duplicate = NULL;

        try {
            $Volume = $this->makeVolumeFixture();

            $this->expectException(QueryException::class);
            $Duplicate = $this->makeVolumeFixture('strongs', $Volume->module);
        }
        finally {
            $this->removeVolumeFixture($Duplicate);
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * Only 'strongs' is registered so far, so the second type is written straight to the table:
     * the index, not the type registry, is what is under test.
     */
    public function testSameModuleIsAllowedUnderAnotherType(): void
    {
        $Volume = $Other = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $Other  = $this->makeVolumeFixture('commentary_fixture', $Volume->module);

            $this->assertSame(2, DB::table('volumes')->where('module', $Volume->module)->count());
        }
        finally {
            $this->removeVolumeFixture($Other);
            $this->removeVolumeFixture($Volume);
        }
    }
}
