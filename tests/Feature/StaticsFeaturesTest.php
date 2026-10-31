<?php

namespace Tests\Feature;

use App\Factories\EngineFactory;
use App\Models\Feature;
use App\Models\VolumeTypes\Strongs;
use App\RenderManager;
use Tests\TestCase;

/**
 * The statics 'features' block, and what each API version carries beside it.
 *
 * Read-only against installed content: settings are changed with config() alone, and the one
 * fixture - a Strong's dictionary - is removed again in tearDown.
 */
class StaticsFeaturesTest extends TestCase
{
    /**
     * The flags statics carried before 'features', which v2 keeps
     */
    private const LEGACY_FLAGS = ['audio_enabled', 'download_enabled', 'download_limit', 'download_formats'];

    /**
     * @return \stdClass
     */
    private function statics(int $version): \stdClass
    {
        return EngineFactory::getNewEngine($version)->actionStatics([]);
    }

    public function testV2KeepsTheLegacyKeysBesideTheFeatures(): void
    {
        $statics = (array) $this->statics(2);

        foreach(array_merge(['bibles', 'volumes', 'features'], self::LEGACY_FLAGS) as $key) {
            $this->assertArrayHasKey($key, $statics, $key);
        }

        $this->assertArrayNotHasKey('features_enabled', $statics);
        $this->assertArrayNotHasKey('bibles', $statics['volumes']);
    }

    public function testV3HasTheNewStructureOnly(): void
    {
        $statics = (array) $this->statics(3);

        $this->assertArrayHasKey('features', $statics);
        $this->assertSame('KJV', $statics['volumes']['bibles']['kjv']['shortname']);

        foreach(array_merge(['bibles', 'features_enabled'], self::LEGACY_FLAGS) as $key) {
            $this->assertArrayNotHasKey($key, $statics, $key);
        }
    }

    public function testEveryFeatureHasAnEnabledFlag(): void
    {
        $features = $this->statics(3)->features;

        $this->assertSame(['audio', 'download', 'cross_references', 'strongs'], array_keys($features));

        foreach($features as $feature => $settings) {
            $this->assertIsBool($settings['enabled'], $feature);
        }
    }

    public function testFeaturesFollowTheConfig(): void
    {
        foreach([TRUE, FALSE] as $enabled) {
            config(['audio.enable' => $enabled, 'download.enable' => $enabled, 'download.bible_limit' => 7]);

            foreach([2, 3] as $version) {
                $features = $this->statics($version)->features;

                $this->assertSame($enabled, $features['audio']['enabled']);
                $this->assertSame($enabled, $features['download']['enabled']);
                $this->assertSame($enabled ? 7 : FALSE, $features['download']['limit']);
                $this->assertSame($enabled ? array_values(RenderManager::getGroupedRendererList()) : [], $features['download']['formats']);
            }
        }
    }

    /**
     * v2's legacy flags are the same values as its features block
     */
    public function testV2LegacyFlagsMatchTheFeatures(): void
    {
        foreach([TRUE, FALSE] as $enabled) {
            config(['audio.enable' => $enabled, 'download.enable' => $enabled]);

            $statics  = $this->statics(2);
            $download = $statics->features['download'];

            $this->assertSame($enabled, (bool) $statics->audio_enabled);
            $this->assertSame($download['enabled'], $statics->download_enabled);
            $this->assertSame($download['limit'], $statics->download_limit);
            $this->assertSame($download['formats'], $statics->download_formats);
        }
    }

    public function testCrossReferencesFollowTheFeature(): void
    {
        $this->assertSame(Feature::isEnabled('cross_references'), $this->statics(3)->features['cross_references']['enabled']);
    }

    public function testStrongsFlags(): void
    {
        $strongs = $this->statics(3)->features['strongs'];

        // The legacy format can be answered by a dictionary or by the legacy table (its feature)
        $legacy = Strongs::hasAvailable() || Feature::isEnabled('strongs_legacy');

        $this->assertSame(Strongs::hasAvailable(), $strongs['modern']['enabled']);
        $this->assertSame($legacy, $strongs['legacy']['enabled']);

        // The top level flag is the default version's: v3's modern has no legacy table fallback
        $this->assertSame($strongs['modern']['enabled'], $strongs['enabled']);
        $this->assertSame($legacy, $this->statics(2)->features['strongs']['enabled']);
    }

    /**
     * An installed, enabled dictionary is what turns modern on
     */
    public function testAnEnabledDictionaryEnablesModernStrongs(): void
    {
        $module = $this->fixtureModule('vol_feat_');
        $this->deferCleanup(fn() => $this->removeVolumeModuleFixture('strongs', $module));

        $Volume = new Strongs();
        $Volume->module    = $module;
        $Volume->name      = 'Statics Feature Fixture ' . $module;
        $Volume->shortname = 'StatFeat ' . $module;
        $Volume->language  = 'en';
        $Volume->save();
        $this->assertTrue($Volume->install(TRUE, TRUE));

        $strongs = $this->statics(3)->features['strongs'];

        $this->assertTrue(Strongs::hasAvailable());
        $this->assertTrue($strongs['modern']['enabled']);
        $this->assertTrue($strongs['enabled']);
    }
}
