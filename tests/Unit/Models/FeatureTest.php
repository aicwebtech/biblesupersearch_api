<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Features\FeatureDefinitions;
use App\Models\Feature;

/**
 * Covers the parts of the Feature model that resolve without a database: the code builder,
 * the enabled-state cache, and the guard clauses that short-circuit before any query.
 *
 * The install/uninstall/enable/disable round trips are feature tests.
 */
class FeatureTest extends TestCase
{
    protected function setUp(): void
    {
        Feature::clearEnabledCache();
    }

    protected function tearDown(): void
    {
        Feature::clearEnabledCache();
    }

    public function testInstalledAndEnabledAreCastToBooleans(): void
    {
        $feature = new Feature();
        $feature->setRawAttributes(['installed' => 1, 'enabled' => 0]);

        $this->assertTrue($feature->installed);
        $this->assertFalse($feature->enabled);
    }

    public function testFillableCoversTheSyncedColumns(): void
    {
        $this->assertSame(
            ['code', 'identifier', 'language', 'installed', 'enabled'],
            (new Feature())->getFillable()
        );
    }

    /**
     * A multi-language feature gets one row per language, so its code has to carry the
     * language to stay unique.
     */
    public function testBuildCodeAppendsTheLanguageForMultiLanguageFeatures(): void
    {
        $code = Feature::buildCode('strongs', 'en', FeatureDefinitions::LANGUAGE_MODE_MULTI);

        $this->assertSame('strongs___en', $code);
    }

    public function testBuildCodeOmitsTheLanguageForSingleLanguageFeatures(): void
    {
        $code = Feature::buildCode('strongs', 'en', FeatureDefinitions::LANGUAGE_MODE_NONE);

        $this->assertSame('strongs', $code);
    }

    /**
     * Multi-language mode with no language still has to produce a usable code - this is the
     * fallback row syncFeatures() creates.
     */
    public function testBuildCodeOmitsTheLanguageWhenNoneIsGiven(): void
    {
        $code = Feature::buildCode('strongs', null, FeatureDefinitions::LANGUAGE_MODE_MULTI);

        $this->assertSame('strongs', $code);
    }

    public function testBuildCodeDefaultsToNoLanguageMode(): void
    {
        $this->assertSame('strongs', Feature::buildCode('strongs', 'en'));
    }

    /**
     * isEnabled() memoises per code+language. Seeding the cache proves the lookup is served
     * from memory - if it were not, this would try to query and fail with no application.
     */
    public function testIsEnabledIsServedFromTheCache(): void
    {
        $property = new \ReflectionProperty(Feature::class, 'is_enabled');
        $property->setValue(null, ['strongs:null' => true, 'strongs:en' => false]);

        $this->assertTrue(Feature::isEnabled('strongs'));
        $this->assertFalse(Feature::isEnabled('strongs', 'en'));
    }

    public function testClearEnabledCacheEmptiesTheCache(): void
    {
        $property = new \ReflectionProperty(Feature::class, 'is_enabled');
        $property->setValue(null, ['strongs:null' => true]);

        Feature::clearEnabledCache();

        $this->assertSame([], $property->getValue());
    }

    /**
     * A feature that was never installed cannot be enabled, and the guard returns before any
     * write - so this holds with no database behind it.
     */
    public function testEnableRefusesWhenTheFeatureIsNotInstalled(): void
    {
        $feature = new Feature();
        $feature->setRawAttributes(['installed' => 0]);

        $this->assertFalse($feature->enable());
    }
}
