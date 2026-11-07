<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Volume;
use App\Models\VolumeTypes\Strongs;
use App\Models\VolumeContent\ContentBase;
use App\Models\VolumeContent\StrongsContent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

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

    /**
     * Drops the content table as well as the row: installing now creates one, and an assertion
     * failure may leave it installed.
     */
    protected function removeVolumeFixture(?Volume $Volume): void
    {
        if(!$Volume) {
            return;
        }

        // Fixtures of an unregistered type have no content class, and so no table
        if(Volume::getContentClassName($Volume->type)) {
            $Volume->content()->uninstall();
        }

        $Volume->forceDelete();
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

    public function testInstallCreatesAndUninstallDropsTheContentTable(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $table  = $Volume->content()->getTable();

            $this->assertSame(ContentBase::CONTENT_TABLE_PREFIX . StrongsContent::TYPE_TABLE_PREFIX . $Volume->module, $table);
            $this->assertFalse(Schema::hasTable($table));

            $this->assertTrue($Volume->install());
            $this->assertTrue(Schema::hasTable($table));
            $this->assertTrue(Schema::hasColumns($table, [
                'id', 'number', 'root_word', 'pronunciation', 'transliteration',
                'definition', 'short_definition', 'is_special', 'created_at', 'updated_at',
            ]));
            $this->assertSame(0, DB::table($table)->count());

            $this->assertTrue($Volume->uninstall());
            $this->assertFalse(Schema::hasTable($table));
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * A table left behind (a failed drop, a deleted volume with the same module) is not taken
     * as installed content: install rebuilds it empty.
     */
    public function testInstallReplacesALeftoverTable(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $table  = $Volume->content()->getTable();

            $this->assertTrue($Volume->install());
            DB::table($table)->insert(['number' => 'H1', 'definition' => 'Stale']);

            // Uninstalled in the volumes table, but the content table survives
            $Volume->installed = 0;
            $Volume->save();

            $this->assertTrue($Volume->install());
            $this->assertSame(0, DB::table($table)->count());
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * The longest module validation allows must still produce a usable table, index names included.
     *
     * That is max:50, or less where the connection's table prefix leaves no room for 50 - see
     * Volume::contentTableNameTooLong().
     */
    public function testInstallWithMaxLengthModule(): void
    {
        $Volume = NULL;
        $prefix = DB::getTablePrefix();
        $length = min(50, Volume::MAX_TABLE_NAME_LENGTH - strlen($prefix . StrongsContent::getContentTableName('strongs', '')));

        // The module needs 8 characters for its unique stem: 'vf' and 6 hex
        if($length < 8) {
            $this->markTestSkipped('The table prefix \'' . $prefix . '\' leaves no room for a fixture module name');
        }

        try {
            $Volume = $this->makeVolumeFixture('strongs', 'vf' . bin2hex(random_bytes(3)) . str_repeat('x', $length - 8));
            $this->assertSame($length, strlen($Volume->module));
            $this->assertFalse(Volume::contentTableNameTooLong('strongs', $Volume->module, $prefix));

            if($length < 50) {
                $this->assertTrue(Volume::contentTableNameTooLong('strongs', $Volume->module . 'x', $prefix));
            }

            $this->assertTrue($Volume->install());
            $this->assertTrue(Schema::hasTable($Volume->content()->getTable()));
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * SQLite needs index names unique across the whole database, not just per table, so two
     * installed volumes of the same type must not share one.
     */
    public function testContentTableIndexNamesAreShortAndDistinct(): void
    {
        $Volume = $Other = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $Other  = $this->makeVolumeFixture();

            $this->assertTrue($Volume->install());
            $this->assertTrue($Other->install(), 'Second volume of the same type failed to install');

            $names = [];

            foreach([$Volume, $Other] as $V) {
                $unique = collect(Schema::getIndexes($V->content()->getTable()))
                    ->first(fn($index) => $index['unique'] && $index['columns'] === ['number']);

                $this->assertNotNull($unique, 'No unique index on number');
                $this->assertLessThanOrEqual(Volume::MAX_TABLE_NAME_LENGTH, strlen($unique['name']));
                $names[] = $unique['name'];
            }

            $this->assertNotSame($names[0], $names[1]);
        }
        finally {
            $this->removeVolumeFixture($Other);
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * A schema that fails after the table is created must not leave the table behind: a later
     * install would see it and report success.
     */
    public function testFailedSchemaLeavesNoTable(): void
    {
        $Volume = new Volume();
        $Volume->type = 'strongs';
        $Volume->module = 'vol_broken_' . bin2hex(random_bytes(3));

        $Content = new class extends ContentBase {
            protected function createSchema(Blueprint $table): void
            {
                $table->increments('id');
                $table->integer('a');

                // A duplicate index name fails after the table is created, on MySQL and SQLite alike.
                // (An index on a missing column does not: SQLite takes an unknown quoted name as a string.)
                $table->index('a', static::indexName($table, 'dup', 'ix'));
                $table->index('id', static::indexName($table, 'dup', 'ix'));
            }
        };

        $Content->setVolume($Volume);
        $table = $Content->getTable();

        try {
            $this->assertFalse($Content->install());
            $this->assertFalse(Schema::hasTable($table), 'Partly built content table was left behind');
        }
        finally {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Same ordering as Bible::uninstall(): a concurrent request must never see an installed
     * volume whose content table is gone.
     */
    public function testUninstallClearsFlagsBeforeDroppingTheContentTable(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $Volume->install(FALSE, TRUE);

            $table = $Volume->content()->getTable();
            $flagsAtDrop = NULL;

            DB::listen(function($query) use ($Volume, $table, &$flagsAtDrop) {
                if($flagsAtDrop === NULL && stripos($query->sql, 'drop table') !== FALSE && str_contains($query->sql, $table)) {
                    $flagsAtDrop = (array) DB::table('volumes')->where('id', $Volume->id)->first(['installed', 'enabled']);
                }
            });

            $Volume->uninstall();

            $this->assertNotNull($flagsAtDrop, 'Content table was never dropped');
            $this->assertSame(0, (int) $flagsAtDrop['installed']);
            $this->assertSame(0, (int) $flagsAtDrop['enabled']);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * A content table that cannot be dropped: the volume goes back to installed and enabled,
     * still owning its table, and the caller is told
     */
    public function testUninstallPutsTheFlagsBackWhenTheTableCannotBeDropped(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $Volume->install(FALSE, TRUE);
            $Volume->refresh();
            $installed_at = $Volume->installed_at;
            $table = $Volume->content()->getTable();

            $Volume->Content = new class extends StrongsContent {
                public function uninstall(): bool
                {
                    throw new \Illuminate\Database\QueryException('mysql', 'DROP TABLE x', [], new \Exception('Lock wait timeout'));
                }
            };
            $Volume->Content->setVolume($Volume);

            $this->assertFalse($Volume->uninstall());
            $this->assertStringContainsString('Could not remove the content table', implode(' ', $Volume->getErrors()));

            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->installed);
            $this->assertSame(1, (int) $Volume->enabled);
            $this->assertEquals($installed_at, $Volume->installed_at);
            $this->assertTrue(Schema::hasTable($table));
        }
        finally {
            if($Volume) {
                $Volume->Content = NULL;
            }

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

    public function testTypeSubclassQueriesOnlyItsOwnType(): void
    {
        $Volume = $Other = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $Other  = $this->makeVolumeFixture('commentary_fixture', $Volume->module);

            $this->assertSame(2, Volume::where('module', $Volume->module)->count());
            $this->assertSame(1, Strongs::where('module', $Volume->module)->count());
            $this->assertSame($Volume->id, Strongs::findByModule($Volume->module)?->id);
            $this->assertNotContains('commentary_fixture', Strongs::all()->pluck('type')->unique()->all());
            $this->assertNull(Strongs::find($Other->id));
        }
        finally {
            $this->removeVolumeFixture($Other);
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testTypeSubclassCreatesAndUpdatesItsOwnType(): void
    {
        $Volume = NULL;

        try {
            $suffix = bin2hex(random_bytes(3));

            $Volume = new Strongs();
            $Volume->module    = 'vol_strongs_' . $suffix;
            $Volume->name      = 'Strongs Subclass Fixture ' . $suffix;
            $Volume->shortname = 'StrSub ' . $suffix;
            $Volume->language  = 'en';
            $Volume->save();

            $Volume->name = $Volume->name . ' Updated';
            $Volume->save();

            $Stored = Volume::find($Volume->id);
            $this->assertSame('strongs', $Stored->type);
            $this->assertStringEndsWith(' Updated', $Stored->name);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * A usable volume of an unregistered type: the default flag is generic, and using a type of
     * its own keeps these tests away from the Strong's default that StrongsApiTest relies on
     * when the suite runs in parallel.
     */
    protected function makeUsableDefaultFixture(): Volume
    {
        $Volume = $this->makeVolumeFixture(self::DEFAULT_FIXTURE_TYPE);
        $Volume->installed = 1;
        $Volume->enabled = 1;
        $Volume->save();

        return $Volume;
    }

    private const DEFAULT_FIXTURE_TYPE = 'default_fixture';

    public function testMakeDefaultNeedsAnInstalledEnabledVolume(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture(self::DEFAULT_FIXTURE_TYPE);

            $this->assertFalse($Volume->makeDefault());
            $this->assertNotEmpty($Volume->getErrors());
            $this->assertSame(0, (int) $Volume->refresh()->is_default);
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    public function testOnlyOneDefaultPerType(): void
    {
        $First = $Second = $Other = NULL;

        try {
            $First  = $this->makeUsableDefaultFixture();
            $Second = $this->makeUsableDefaultFixture();

            $this->assertTrue($First->makeDefault());
            $this->assertTrue($First->isDefault());
            $this->assertSame($First->id, Volume::getDefault(self::DEFAULT_FIXTURE_TYPE)?->id);

            $this->assertTrue($Second->makeDefault());
            $this->assertSame(0, (int) $First->refresh()->is_default);
            $this->assertSame($Second->id, Volume::getDefault(self::DEFAULT_FIXTURE_TYPE)?->id);
            $this->assertSame(1, Volume::where('type', self::DEFAULT_FIXTURE_TYPE)->where('is_default', 1)->count());
        }
        finally {
            $this->removeVolumeFixture($Second);
            $this->removeVolumeFixture($First);
        }
    }

    public function testGetDefaultIgnoresADisabledDefault(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeUsableDefaultFixture();
            $Volume->makeDefault();
            $Volume->disable();

            $this->assertNull(Volume::getDefault(self::DEFAULT_FIXTURE_TYPE));
        }
        finally {
            $this->removeVolumeFixture($Volume);
        }
    }

    /**
     * Volumes of a type of their own, unregistered and unique to this test, so no other test -
     * in this run or a parallel one - has a default of it: the first volume enabled claims it.
     * The content table is a no-op stand-in, as an unregistered type has no content class; it
     * reports rows unless made with $rows FALSE.
     *
     * @return array{0: string, 1: \Closure(bool=): Volume}
     */
    protected function claimFixtures(): array
    {
        $type = 'claim_fixture_' . bin2hex(random_bytes(3));

        $make = function(bool $rows = TRUE) use ($type): Volume {
            $Volume = $this->makeVolumeFixture($type);
            $Volume->Content = new class extends StrongsContent {
                public bool $rows = TRUE;

                public function install(bool $structure_only = FALSE): bool
                {
                    return TRUE;
                }

                public function hasRows(): bool
                {
                    return $this->rows;
                }
            };
            $Volume->Content->rows = $rows;

            return $Volume;
        };

        return [$type, $make];
    }

    /**
     * Installing with enable, or enabling, makes a volume the default of its type when the type
     * has no usable default - the first dictionary an admin installs is the one a request naming
     * none is answered from.  A type that has a default keeps it.
     */
    public function testInstallingOrEnablingTheFirstVolumeMakesItTheDefault(): void
    {
        [$type, $make] = $this->claimFixtures();
        $Installed = $Enabled = $Second = $Disabled = NULL;

        try {
            // Installed without enabling: not usable, so not the default
            $Disabled = $make();
            $this->assertTrue($Disabled->install(FALSE, FALSE));
            $this->assertNull(Volume::getDefault($type));

            $Installed = $make();
            $this->assertTrue($Installed->install(FALSE, TRUE));
            $this->assertSame($Installed->id, Volume::getDefault($type)?->id);

            // A second one leaves the default alone
            $Second = $make();
            $this->assertTrue($Second->install(FALSE, TRUE));
            $Disabled->enable();
            $this->assertSame($Installed->id, Volume::getDefault($type)?->id);
            $this->assertSame(1, Volume::where('type', $type)->where('is_default', 1)->count());

            // Enabling claims it too, once the type has no usable default
            DB::table('volumes')->where('type', $type)->update(['is_default' => 0]);
            $Enabled = $make();
            $Enabled->installed = 1;
            $Enabled->save();
            $Enabled->enable();

            $this->assertSame($Enabled->id, Volume::getDefault($type)?->id);
        }
        finally {
            foreach([$Installed, $Enabled, $Second, $Disabled] as $Volume) {
                $Volume?->forceDelete(); // unregistered type: no content table to drop
            }
        }
    }

    /**
     * A volume with no content - installed without a module file - does not become the default:
     * it would answer every request 'not found'.  Once it has rows, enabling it claims it.
     */
    public function testAnEmptyVolumeDoesNotBecomeTheDefault(): void
    {
        [$type, $make] = $this->claimFixtures();
        $Empty = NULL;

        try {
            $Empty = $make(FALSE);
            $this->assertTrue($Empty->install(FALSE, TRUE));
            $this->assertNull(Volume::getDefault($type));

            $Empty->disable();
            $Empty->Content->rows = TRUE;
            $Empty->enable();

            $this->assertSame($Empty->id, Volume::getDefault($type)?->id);
        }
        finally {
            $Empty?->forceDelete(); // unregistered type: no content table to drop
        }
    }

    /**
     * A Strong's dictionary installed where one is already the default does not take its place
     */
    public function testInstallingKeepsTheExistingStrongsDefault(): void
    {
        $Default = Volume::getDefault('strongs');

        if(!$Default) {
            $this->markTestSkipped('No default Strong\'s dictionary is installed');
        }

        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture();
            $this->assertTrue($Volume->install(FALSE, TRUE));

            $this->assertSame(0, (int) $Volume->refresh()->is_default);
            $this->assertSame($Default->id, Volume::getDefault('strongs')?->id);
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
