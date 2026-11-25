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
     * A throwaway Strong's dictionary in the fixture language.  One left uninstalled still has
     * content waiting in its table, as a fixture has no module file: without either, there is
     * nothing to install (Volume::install()).
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
            $this->installVolumeFixture($Volume, $enabled);
        }
        else {
            $this->assertTrue($Volume->content()->install(TRUE));
            DB::table($Volume->content()->getTable())->insert(['number' => 'H1', 'definition' => 'Fixture', 'is_special' => 0]);
        }

        return $Volume;
    }

    /**
     * VolumeFeature's protected helpers, for the 'all' actions: those act on every volume of the
     * type, real dictionaries included, so their logic is tested on fixtures only
     */
    private function helpers(): VolumeFeature
    {
        return new class extends VolumeFeature {
            public static function callUninstallVolumes(iterable $Volumes): array
            {
                return static::uninstallVolumes($Volumes);
            }

            public static function callEnableVolumes(iterable $Volumes): array
            {
                return static::enableVolumes($Volumes);
            }

            public static function callDisableVolumes(iterable $Volumes): void
            {
                static::disableVolumes($Volumes);
            }

            public static ?string $hard_default = NULL;

            protected static function hardDefaultModule(string $type): ?string
            {
                return static::$hard_default;
            }

            public static function callPreferringHardDefault(string $type, callable $action): bool
            {
                return static::preferringHardDefault($type, $action);
            }
        };
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
     * For a test that enables fixtures through the feature (installVolumeFixture() doesn't
     * claim the default): with no usable Strong's default, the first fixture enabled would claim it (Volume::claimDefaultIfNone()), and parallel tests resolving the
     * global default would pick a throwaway dictionary.  Only
     * testInstallMakesADefaultWhenTheTypeHasNone() takes the default, inside a rolled back
     * transaction.
     */
    private function requireUsableDefault(): void
    {
        if(!Volume::getDefault('strongs')) {
            $this->markTestSkipped('No usable Strong\'s default here: an enabled fixture would claim it');
        }
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
        $this->requireUsableDefault();

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
        $this->requireUsableDefault();

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

        // No hard default: the installed en_orig would otherwise take the cleared default
        // (testTheHardDefaultIsPreferredWhenNoDefaultIsFlagged())
        $Feature  = $this->helpers();

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
            $this->assertTrue($Feature::install('strongs', self::LANGUAGE, [$Disabled->module], FALSE));
            $this->assertNull(Volume::getDefault('strongs'));

            $this->assertTrue($Feature::install('strongs', self::LANGUAGE, [$First->module, $Second->module], TRUE));
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
        $this->requireUsableDefault();

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

    /**
     * Install all: each language's presets, as install() would one language at a time.  The
     * presets are passed in, so only fixtures are touched.
     */
    public function testInstallAllInstallsEveryLanguage(): void
    {
        $this->requireUsableDefault();

        $First  = $this->makeVolume();
        $Second = $this->makeVolume(FALSE, FALSE, 'qqg');

        $this->assertTrue(VolumeFeature::installAll('strongs', [self::LANGUAGE => [$First->module], 'qqg' => [$Second->module]], TRUE));

        foreach([$First, $Second] as $Volume) {
            $Volume->refresh();
            $this->assertSame(1, (int) $Volume->installed, $Volume->module);
            $this->assertSame(1, (int) $Volume->enabled, $Volume->module);
        }
    }

    public function testInstallAllInstallsWhatItCanAndReportsTheRest(): void
    {
        $Volume = $this->makeVolume();

        try {
            VolumeFeature::installAll('strongs', [self::LANGUAGE => [$Volume->module], 'qqg' => ['no_such_module']], FALSE);
            $this->fail('No FeatureException');
        }
        catch(FeatureException $e) {
            $this->assertStringContainsString('no_such_module', $e->getMessage());
        }

        $this->assertSame(1, (int) $Volume->refresh()->installed);
        $this->assertSame(0, (int) $Volume->enabled);
    }

    /**
     * An unexpected error in one language (a database error, say) doesn't stop the rest, and
     * isn't shown as is: it can carry database details.  install() is replaced, so nothing is
     * installed.
     */
    public function testInstallAllCarriesOnPastAnUnexpectedError(): void
    {
        $Feature = new class extends VolumeFeature {
            public static array $attempted = [];

            public static function install(string $type, string $language, array $modules, bool $enable): bool
            {
                static::$attempted[] = $language;

                if($language === 'qqf') {
                    throw new \RuntimeException('SQLSTATE secret details');
                }

                return TRUE;
            }
        };

        try {
            $Feature::installAll('strongs', ['qqf' => ['a'], 'qqg' => ['b']], FALSE);
            $this->fail('No FeatureException');
        }
        catch(FeatureException $e) {
            $this->assertSame('qqf: an unexpected error occurred. Details are in the error log.', $e->getMessage());
        }

        $this->assertSame(['qqf', 'qqg'], $Feature::$attempted);
    }

    /**
     * runAll() turns any failure into an error rather than a 500, and refreshes the rows' flags
     * either way: the fixture row claims to be installed, with no volume behind it.
     */
    public function testRunAllReportsAnUnexpectedErrorAndRefreshesTheFlags(): void
    {
        $Row = $this->makeFeatureRow();
        $Row->update(['installed' => 1, 'enabled' => 1]);

        // An anonymous subclass would name its table after itself
        $Feature = new class extends Feature {
            protected $table = 'features';

            public static function callRunAllCallback(callable $callback): array
            {
                return static::runAllCallback($callback);
            }
        };

        $errors = $Feature::callRunAllCallback(function() {
            throw new \RuntimeException('SQLSTATE secret details');
        });

        $this->assertSame(['An unexpected error occurred. Details are in the error log.'], $errors);

        $Row->refresh();
        $this->assertFalse((bool) $Row->installed);
        $this->assertFalse((bool) $Row->enabled);

        $this->assertSame(['Refused'], $Feature::callRunAllCallback(fn() => throw new FeatureException('Refused')));
    }

    /**
     * A volume with no module file and no content cannot be installed by the feature either
     */
    public function testInstallRefusesAVolumeWithNoContent(): void
    {
        $Volume = $this->makeVolume();
        DB::table($Volume->content()->getTable())->delete();

        $this->expectException(FeatureException::class);
        $this->expectExceptionMessage('no content');

        VolumeFeature::install('strongs', self::LANGUAGE, [$Volume->module], TRUE);
    }

    public function testEnableRefusesAVolumeWithNoContent(): void
    {
        $Volume = $this->makeVolume(TRUE);
        DB::table($Volume->content()->getTable())->delete();

        $this->assertSame([$Volume->module . ': Cannot enable ' . $Volume->name . ': it has no content.'], $this->helpers()::callEnableVolumes([$Volume]));
        $this->assertSame(0, (int) $Volume->refresh()->enabled);

        $this->expectException(FeatureException::class);
        VolumeFeature::enable('strongs', self::LANGUAGE);
    }

    /**
     * Uninstall all takes the default too - last - and clears its flag, which uninstall()
     * refuses to do for one language
     */
    public function testUninstallAllUninstallsTheDefaultAndClearsIt(): void
    {
        $Default = $this->makeVolume(TRUE);
        $Other   = $this->makeVolume(TRUE, TRUE, 'qqg');
        $this->flagAsDefault($Default);
        $Default->refresh();

        $this->assertSame([], $this->helpers()::callUninstallVolumes([$Default, $Other]));

        $Default->refresh();
        $this->assertSame(0, (int) $Default->installed);
        $this->assertSame(0, (int) $Default->is_default);
        $this->assertSame(0, (int) $Other->refresh()->installed);
    }

    /**
     * When another volume could not be uninstalled, the default stays installed and the default,
     * so what is left still has one
     */
    public function testUninstallAllKeepsTheDefaultWhenAnotherFails(): void
    {
        $Default = $this->makeVolume(TRUE);
        $this->flagAsDefault($Default);
        $Default->refresh();

        $Stuck = new class extends Strongs {
            public function uninstall(): bool
            {
                return $this->addError('Locked', 4);
            }
        };
        $Stuck->module = 'vol_fixture_stuck';

        $errors = $this->helpers()::callUninstallVolumes([$Default, $Stuck]);

        $this->assertSame(['vol_fixture_stuck: Locked', $Default->module . ': left installed as the default'], $errors);

        $Default->refresh();
        $this->assertSame(1, (int) $Default->installed);
        $this->assertSame(1, (int) $Default->is_default);
    }

    /**
     * Disable all drops the default, as uninstall all does: a disabled default would only block
     * disabling or uninstalling it.  The default fixture stays disabled (see flagAsDefault());
     * the other is enabled, to check it is disabled.
     */
    public function testDisableAllDisablesEverythingAndDropsTheDefault(): void
    {
        $Default = $this->makeVolume(TRUE);
        $Other   = $this->makeVolume(TRUE, TRUE, 'qqg');
        $this->flagAsDefault($Default);
        $Default->refresh();

        $this->helpers()::callDisableVolumes([$Default, $Other]);

        $Default->refresh();
        $this->assertSame(0, (int) $Default->enabled);
        $this->assertSame(1, (int) $Default->installed);
        $this->assertSame(0, (int) $Default->is_default);

        $Other->refresh();
        $this->assertSame(0, (int) $Other->enabled);
        $this->assertSame(1, (int) $Other->installed);
    }

    /**
     * The default is enabled first, whatever order the volumes come in, so it is the default
     * again rather than whichever volume claims the empty slot first.  Unsaved doubles that
     * record the order: enabling real fixtures would make one the type's usable default.
     */
    public function testEnableAllEnablesTheDefaultFirst(): void
    {
        $order   = new \ArrayObject();
        $volumes = [];

        foreach(['first_listed' => 0, 'the_default' => 1, 'last_listed' => 0] as $module => $default) {
            $Volume = new class extends Strongs {
                public ?\ArrayObject $order = NULL;

                public function enable(): bool
                {
                    $this->order[] = $this->module;
                    return TRUE;
                }
            };

            $Volume->order = $order;
            $Volume->setRawAttributes(['module' => $module, 'is_default' => $default, 'installed' => 1, 'enabled' => 0]);
            $volumes[] = $Volume;
        }

        $this->assertSame([], $this->helpers()::callEnableVolumes($volumes));
        $this->assertSame('the_default', $order[0]);
        $this->assertCount(3, $order);
    }

    /**
     * Uninstall all names, for the admin to confirm, the volumes it would lose for good: those
     * installed with no module file to install them again from.  A fixture has none.
     */
    public function testUninstallAllLossesNameTheVolumesWithNoModuleFile(): void
    {
        $Installed   = $this->makeVolume(TRUE);
        $Uninstalled = $this->makeVolume();

        $losses = VolumeFeature::uninstallAllLosses('strongs');

        $this->assertContains($Installed->name . ' (' . $Installed->module . ')', $losses);
        $this->assertNotContains($Uninstalled->name . ' (' . $Uninstalled->module . ')', $losses);

        foreach(Strongs::where('installed', 1)->get() as $Volume) {
            if($Volume->hasModuleFile()) {
                $this->assertNotContains($Volume->name . ' (' . $Volume->module . ')', $losses);
            }
        }

        $response = $this->actingAs($this->admin())->withSession(['banned' => FALSE])
            ->getJson('/admin/features/all/uninstall/strongs/losses')
            ->assertStatus(200);

        $this->assertContains($Installed->name . ' (' . $Installed->module . ')', $response['losses']);

        // Nothing to lose: no check defined for the action, or for the feature
        foreach(['/admin/features/all/disable/strongs/losses', '/admin/features/all/uninstall/strongs_legacy/losses'] as $url) {
            $this->actingAs($this->admin())->withSession(['banned' => FALSE])->getJson($url)
                ->assertStatus(200)
                ->assertJson(['success' => TRUE, 'losses' => []]);
        }
    }

    /**
     * With no default flagged, the hard default becomes the default once the action leaves it
     * enabled, even after another volume claimed the slot first.  A default already flagged is
     * kept.
     *
     * Inside a rolled back transaction, as testInstallMakesADefaultWhenTheTypeHasNone(): the
     * real default's flag is cleared.  The fixtures are made first, as their tables' DDL
     * would commit it.
     */
    public function testTheHardDefaultIsPreferredWhenNoDefaultIsFlagged(): void
    {
        $Low     = $this->makeVolume(TRUE);
        $Hard    = $this->makeVolume(TRUE);
        $helpers = $this->helpers();
        $helpers::$hard_default = $Hard->module;

        if(DB::connection()->getDriverName() === 'mysql') {
            DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        }

        DB::beginTransaction();

        try {
            DB::table('volumes')->where('type', 'strongs')->where('is_default', 1)->update(['is_default' => 0]);

            // $Low is enabled first, so claims the default, then gives it up
            $this->assertTrue($helpers::callPreferringHardDefault('strongs', fn(): bool => !$helpers::callEnableVolumes([$Low, $Hard])));
            $this->assertSame($Hard->id, Volume::getDefault('strongs')?->id);
            $this->assertSame(0, (int) $Low->refresh()->is_default);

            // A flagged default, even one disabled until the action enables it, is kept
            $Low->disable();
            $Hard->refresh()->disable();
            DB::table('volumes')->where('type', 'strongs')->update(['is_default' => 0]);
            DB::table('volumes')->where('id', $Low->id)->update(['is_default' => 1]);

            $this->assertTrue($helpers::callPreferringHardDefault('strongs', fn(): bool => !$helpers::callEnableVolumes([$Hard->refresh(), $Low->refresh()])));
            $this->assertSame($Low->id, Volume::getDefault('strongs')?->id);
            $this->assertSame(0, (int) $Hard->refresh()->is_default);
        }
        finally {
            DB::rollBack();
            $helpers::$hard_default = NULL;
        }
    }

    /**
     * Strong's hard default is the dictionary a new installation starts with
     */
    public function testStrongsHardDefaultIsTheInstallDefault(): void
    {
        $this->assertSame(\App\InstallManager::DEFAULT_STRONGS_MODULE, Strongs::hardDefaultModule());
        $this->assertSame('en_orig', Strongs::hardDefaultModule());
    }

    /**
     * The 'all' route refuses a feature that has no such action, and an unknown feature
     */
    public function testTheAllRouteRefusesWhatItCannotDo(): void
    {
        foreach(['/admin/features/all/uninstall/strongs_legacy', '/admin/features/all/install/no_such_feature'] as $url) {
            $this->actingAs($this->admin())->withSession(['banned' => FALSE])->postJson($url)
                ->assertStatus(422)
                ->assertJson(['success' => FALSE]);
        }

        $this->actingAs($this->admin())->withSession(['banned' => FALSE])->postJson('/admin/features/all/delete/strongs')
            ->assertStatus(404);
    }
}
