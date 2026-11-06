<?php

namespace Tests\Feature\Features;

use Tests\TestCase;
use App\Features\FeatureException;
use App\Features\VolumeFeature;
use App\Models\Feature;
use App\Models\Volume;
use App\Models\VolumeTypes\Strongs;
use Illuminate\Support\Facades\DB;

/**
 * Features page shortcuts over the Strong's dictionaries of one language, and the feature rows
 * that use them.
 *
 * Everything happens in a throwaway language (qq?, swept by TestCleanup) with throwaway
 * dictionaries passed as the module list: the real dictionaries and presets are never touched.
 */
class VolumeFeatureTest extends TestCase
{
    private const LANGUAGE = 'qqf';

    /**
     * A throwaway Strong's dictionary in the fixture language
     */
    private function makeVolume(bool $installed = FALSE, bool $enabled = FALSE, string $language = self::LANGUAGE): Strongs
    {
        $module = $this->fixtureModule('vol_fixture_');
        $this->deferCleanup(fn() => $this->removeVolumeModuleFixture('strongs', $module));

        $Volume = new Strongs();
        $Volume->module    = $module;
        $Volume->name      = 'Volume Feature Fixture ' . $module;
        $Volume->shortname = 'VolFeat ' . $module;
        $Volume->language  = $language;
        $Volume->save();

        if($installed) {
            $this->assertTrue($Volume->install(TRUE, $enabled));
        }

        return $Volume;
    }

    /**
     * The Strong's feature row of the fixture language
     */
    private function makeFeatureRow(): Feature
    {
        $code = Feature::buildCode('strongs', self::LANGUAGE, 'multi');
        $this->deferCleanup(fn() => Feature::where('code', $code)->delete());

        return Feature::create(['code' => $code, 'identifier' => 'strongs', 'language' => self::LANGUAGE, 'installed' => 0, 'enabled' => 0]);
    }

    /**
     * Flags the volume as its type's default, without making it the one any request resolves
     * to: it stays disabled, and Volume::getDefault() only returns an enabled default.  Done
     * directly, as makeDefault() would clear the real default.
     */
    private function flagAsDefault(Volume $Volume): void
    {
        $this->assertFalse((bool) $Volume->enabled);
        DB::table('volumes')->where('id', $Volume->id)->update(['is_default' => 1]);
    }

    /**
     * Unsaved, as in FeatureControllerTest
     */
    private function admin(): \App\User
    {
        $User = new \App\User;
        $User->name = 'Test Admin';
        $User->access_level = 100;

        return $User;
    }

    public function testStatusIsDerivedFromTheVolumes(): void
    {
        $this->assertSame(['installed' => FALSE, 'enabled' => FALSE], VolumeFeature::status('strongs', self::LANGUAGE));

        $this->makeVolume();
        $this->assertSame(['installed' => FALSE, 'enabled' => FALSE], VolumeFeature::status('strongs', self::LANGUAGE));

        $this->makeVolume(TRUE);
        $this->assertSame(['installed' => TRUE, 'enabled' => FALSE], VolumeFeature::status('strongs', self::LANGUAGE));

        $this->makeVolume(TRUE, TRUE);
        $this->assertSame(['installed' => TRUE, 'enabled' => TRUE], VolumeFeature::status('strongs', self::LANGUAGE));
    }

    public function testInstallInstallsAndEnablesTheListedModulesOnly(): void
    {
        $First    = $this->makeVolume();
        $Second   = $this->makeVolume();
        $Unlisted = $this->makeVolume();

        $this->assertTrue(VolumeFeature::install('strongs', self::LANGUAGE, [$First->module, $Second->module], TRUE));

        foreach([$First, $Second] as $Volume) {
            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->installed, $Volume->module);
            $this->assertSame(1, (int) $Volume->enabled, $Volume->module);
        }

        $this->assertSame(0, (int) $Unlisted->refresh()->installed);

        // Again: what is installed is left alone
        $this->assertTrue(VolumeFeature::install('strongs', self::LANGUAGE, [$First->module], FALSE));
        $this->assertSame(1, (int) $First->refresh()->enabled);
    }

    /**
     * Install & enable also enables a listed module that was installed and left disabled
     */
    public function testInstallEnablesAnInstalledButDisabledModule(): void
    {
        $Volume = $this->makeVolume(TRUE);
        $this->assertSame(0, (int) $Volume->enabled);

        $this->assertTrue(VolumeFeature::install('strongs', self::LANGUAGE, [$Volume->module], TRUE));
        $this->assertSame(1, (int) $Volume->refresh()->enabled);
    }

    /**
     * With no usable default, the first listed module that ends up installed and enabled
     * becomes it; an existing default is kept.
     *
     * The real default is cleared inside a transaction that is rolled back, at READ COMMITTED
     * as in StrongsApiTest::testGlobalDefaultAndNoDictionary().  The fixtures are installed
     * before it, because creating their content tables would commit it.
     */
    public function testInstallMakesADefaultWhenTheTypeHasNone(): void
    {
        $Disabled = $this->makeVolume(TRUE);
        $First    = $this->makeVolume(TRUE);
        $Second   = $this->makeVolume(TRUE);
        $before   = Volume::getDefault('strongs')?->id;

        // A usable default is kept
        if($before) {
            $this->assertTrue(VolumeFeature::install('strongs', self::LANGUAGE, [$First->module], TRUE));
            $this->assertSame($before, Volume::getDefault('strongs')?->id);
            $this->assertSame(0, (int) $First->refresh()->is_default);
            $First->disable();
        }

        if(DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        DB::beginTransaction();

        try {
            DB::table('volumes')->where('type', 'strongs')->where('is_default', 1)->update(['is_default' => 0]);

            // Installed without enabling: nothing can be the default
            $this->assertTrue(VolumeFeature::install('strongs', self::LANGUAGE, [$Disabled->module], FALSE));
            $this->assertNull(Volume::getDefault('strongs'));

            $this->assertTrue(VolumeFeature::install('strongs', self::LANGUAGE, [$First->module, $Second->module], TRUE));
            $this->assertSame($First->id, Volume::getDefault('strongs')?->id);
            $this->assertSame(0, (int) $Second->refresh()->is_default);
        }
        finally {
            DB::rollBack();
        }
    }

    public function testInstallWithoutEnablingLeavesThemDisabled(): void
    {
        $Volume = $this->makeVolume();

        $this->assertTrue(VolumeFeature::install('strongs', self::LANGUAGE, [$Volume->module], FALSE));
        $this->assertSame(['installed' => TRUE, 'enabled' => FALSE], VolumeFeature::status('strongs', self::LANGUAGE));
    }

    public function testInstallRefusesUnknownModulesAndOtherLanguages(): void
    {
        $Good  = $this->makeVolume();
        $Other = $this->makeVolume(FALSE, FALSE, 'qqg');
        $none  = $this->fixtureModule('vol_fixture_');

        try {
            VolumeFeature::install('strongs', self::LANGUAGE, [$Good->module, $Other->module, $none], TRUE);
            $this->fail('No FeatureException');
        }
        catch(FeatureException $e) {
            $this->assertStringContainsString($Other->module . ': is not in this language', $e->getMessage());
            $this->assertStringContainsString($none . ': no volume or module file found', $e->getMessage());
        }

        // The good one is still installed; the other language's is not
        $this->assertSame(1, (int) $Good->refresh()->installed);
        $this->assertSame(0, (int) $Other->refresh()->installed);
    }

    public function testInstallRefusesAnEmptyList(): void
    {
        $this->expectException(FeatureException::class);

        VolumeFeature::install('strongs', self::LANGUAGE, [], TRUE);
    }

    public function testEnableEnablesEveryInstalledVolumeOnly(): void
    {
        $Installed   = $this->makeVolume(TRUE);
        $Uninstalled = $this->makeVolume();

        $this->assertTrue(VolumeFeature::enable('strongs', self::LANGUAGE));

        $this->assertSame(1, (int) $Installed->refresh()->enabled);
        $this->assertSame(0, (int) $Uninstalled->refresh()->enabled);
    }

    public function testEnableRefusesWhenNothingIsInstalled(): void
    {
        $this->makeVolume();
        $this->expectException(FeatureException::class);

        VolumeFeature::enable('strongs', self::LANGUAGE);
    }

    public function testDisableAndUninstallActOnEveryVolumeOfTheLanguage(): void
    {
        $First  = $this->makeVolume(TRUE, TRUE);
        $Second = $this->makeVolume(TRUE, TRUE);
        $Other  = $this->makeVolume(TRUE, TRUE, 'qqg');

        $this->assertTrue(VolumeFeature::disable('strongs', self::LANGUAGE));
        $this->assertSame(['installed' => TRUE, 'enabled' => FALSE], VolumeFeature::status('strongs', self::LANGUAGE));

        $this->assertTrue(VolumeFeature::uninstall('strongs', self::LANGUAGE));
        $this->assertSame(['installed' => FALSE, 'enabled' => FALSE], VolumeFeature::status('strongs', self::LANGUAGE));

        foreach([$First, $Second] as $Volume) {
            $this->assertSame(0, (int) $Volume->refresh()->installed, $Volume->module);
        }

        // Another language is untouched
        $Other->refresh();
        $this->assertSame(1, (int) $Other->installed);
        $this->assertSame(1, (int) $Other->enabled);
    }

    public function testDisableAndUninstallRefuseWhileTheLanguageHoldsTheDefault(): void
    {
        $Default = $this->makeVolume(TRUE);
        $Enabled = $this->makeVolume(TRUE, TRUE);
        $this->flagAsDefault($Default);

        foreach(['disable', 'uninstall'] as $action) {
            try {
                VolumeFeature::$action('strongs', self::LANGUAGE);
                $this->fail($action . ': no FeatureException');
            }
            catch(FeatureException $e) {
                $this->assertStringContainsString('Cannot ' . $action, $e->getMessage());
                $this->assertStringContainsString($Default->name, $e->getMessage());
            }
        }

        // Nothing was changed
        $this->assertSame(1, (int) $Default->refresh()->installed);
        $this->assertSame(1, (int) $Enabled->refresh()->enabled);
    }

    /**
     * The feature row's flags follow the volumes, however they were changed
     */
    public function testRefreshDerivedWritesTheLiveFlags(): void
    {
        $Feature = $this->makeFeatureRow();
        $this->makeVolume(TRUE, TRUE);

        Feature::refreshDerived();

        $Feature->refresh();
        $this->assertTrue($Feature->installed);
        $this->assertTrue($Feature->enabled);
    }

    /**
     * Volumes changed elsewhere (the Volumes page) show when the Features page is loaded.
     *
     * That the grid requests do not refresh is not asserted: another worker's page load
     * (FeatureControllerTest) refreshes every row.
     */
    public function testThePageLoadRefreshesTheDerivedFlags(): void
    {
        $Feature = $this->makeFeatureRow();
        $this->makeVolume(TRUE, TRUE);

        $this->actingAs($this->admin())->withSession(['banned' => FALSE])->get('/admin/features')->assertOk();

        $Feature->refresh();
        $this->assertTrue($Feature->installed);
        $this->assertTrue($Feature->enabled);
    }

    public function testFeatureRowEnableAndDisableActOnTheVolumes(): void
    {
        $Feature = $this->makeFeatureRow();
        $Volume  = $this->makeVolume(TRUE);

        $this->assertTrue($Feature->enable(), implode(' ', $Feature->getErrors()));
        $this->assertSame(1, (int) $Volume->refresh()->enabled);
        $this->assertTrue($Feature->refresh()->enabled);

        $this->assertTrue($Feature->disable(), implode(' ', $Feature->getErrors()));
        $this->assertSame(0, (int) $Volume->refresh()->enabled);
        $this->assertFalse($Feature->refresh()->enabled);
        $this->assertTrue($Feature->installed);

        $this->assertTrue($Feature->uninstall(), implode(' ', $Feature->getErrors()));
        $this->assertSame(0, (int) $Volume->refresh()->installed);
        $this->assertFalse($Feature->refresh()->installed);
    }

    /**
     * A refused action reaches the admin as the feature's error, and the flags stay derived
     */
    public function testFeatureRowReportsWhyAnActionWasRefused(): void
    {
        $Feature = $this->makeFeatureRow();

        // No presets are listed for the fixture language
        $this->assertFalse($Feature->install(TRUE));
        $this->assertStringContainsString('No Strong\'s Dictionary volumes are listed', implode(' ', $Feature->getErrors()));
        $this->assertFalse($Feature->refresh()->installed);

        $Default = $this->makeVolume(TRUE);
        $this->flagAsDefault($Default);

        $Feature = Feature::find($Feature->id);
        $this->assertFalse($Feature->uninstall());
        $this->assertStringContainsString('Cannot uninstall', implode(' ', $Feature->getErrors()));
        $this->assertTrue($Feature->refresh()->installed);
    }

    public function testTheControllerReturnsTheRefusal(): void
    {
        $Feature = $this->makeFeatureRow();

        $response = $this->actingAs($this->admin())->withSession(['banned' => FALSE])->postJson('/admin/features/install/' . $Feature->id);

        $response->assertStatus(422);
        $this->assertStringContainsString('No Strong\'s Dictionary volumes are listed', implode(' ', $response['errors']));
    }
}
