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
     * The longest module validation allows must still produce a usable table, index names included.
     */
    public function testInstallWithMaxLengthModule(): void
    {
        $Volume = NULL;

        try {
            $Volume = $this->makeVolumeFixture('strongs', 'vf' . bin2hex(random_bytes(3)) . str_repeat('x', 42));
            $this->assertSame(50, strlen($Volume->module));

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
