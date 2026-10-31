<?php

namespace Tests\Feature\Features;

use Tests\TestCase;
use App\Features\FeatureDefinitions;

class FeatureDefinitionsTest extends TestCase
{
    public function testAllReturnsExpectedFeatures()
    {
        $definitions = FeatureDefinitions::all();

        $this->assertIsArray($definitions);
        $this->assertGreaterThanOrEqual(2, count($definitions));

        $identifiers = array_column($definitions, 'identifier');
        $this->assertContains('cross_references', $identifiers);
        $this->assertContains('strongs', $identifiers);
    }

    public function testAllReturnsCrossReferencesWithNullLanguage()
    {
        $definitions = FeatureDefinitions::all();
        $crossRef = collect($definitions)->firstWhere('identifier', 'cross_references');

        $this->assertNotNull($crossRef);
        $this->assertNull($crossRef['languages']);
        $this->assertEquals('Cross References', $crossRef['name']);
    }

    public function testAllReturnsStrongsWithMultipleLanguages()
    {
        $definitions = FeatureDefinitions::all();
        $strongs = collect($definitions)->firstWhere('identifier', 'strongs');

        $this->assertNotNull($strongs);
        $this->assertIsArray($strongs['languages']);
        $this->assertContains('en', $strongs['languages']);
        $this->assertContains('ru', $strongs['languages']);
        $this->assertContains('es', $strongs['languages']);
    }

    public function testFindReturnsCorrectDefinition()
    {
        $definition = FeatureDefinitions::find('strongs');

        $this->assertNotNull($definition);
        $this->assertEquals('strongs', $definition['identifier']);
        $this->assertEquals("Strong's Dictionaries", $definition['name']);
    }

    public function testFindReturnsNullForUnknown()
    {
        $definition = FeatureDefinitions::find('nonexistent_feature');

        $this->assertNull($definition);
    }

    public function testDefinitionsHaveRequiredFields()
    {
        $definitions = FeatureDefinitions::all();

        foreach ($definitions as $definition) {
            $this->assertArrayHasKey('identifier', $definition);
            $this->assertArrayHasKey('name', $definition);
            $this->assertArrayHasKey('description', $definition);
            $this->assertArrayHasKey('languages', $definition);
            $this->assertArrayHasKey('install', $definition);
            $this->assertArrayHasKey('uninstall', $definition);
        }
    }

    public function testInstallAndUninstallCallbacksAreCallable()
    {
        $definitions = FeatureDefinitions::all();

        foreach ($definitions as $definition) {
            $this->assertTrue(is_callable($definition['install']));
            $this->assertTrue(is_callable($definition['uninstall']));
        }
    }

    /**
     * Checked by reflection, never run: the legacy callbacks would truncate installed data.
     *
     * install takes (?string $language, bool $enable); uninstall, enable and disable take
     * (?string $language); all return bool.  derived takes (?string $language) and returns array.
     */
    public function testCallbacksMatchContractWithoutExecution()
    {
        $contracts = ['install' => 2, 'uninstall' => 1, 'enable' => 1, 'disable' => 1, 'derived' => 1];

        foreach (FeatureDefinitions::all() as $definition) {
            foreach ($contracts as $callbackKey => $count) {
                if (!array_key_exists($callbackKey, $definition)) {
                    $this->assertContains($callbackKey, ['enable', 'disable', 'derived'], "{$definition['identifier']} has no {$callbackKey} callback");
                    continue;
                }

                $label = "{$definition['identifier']} {$callbackKey} callback";
                $this->assertIsCallable($definition[$callbackKey]);

                $reflection = new \ReflectionFunction(\Closure::fromCallable($definition[$callbackKey]));
                $this->assertSame($count, $reflection->getNumberOfParameters(), "{$label} must accept {$count} parameter(s)");

                $language = $reflection->getParameters()[0];
                $this->assertTrue($language->allowsNull(), "{$label} language parameter must allow null");
                $this->assertSame('string', $language->getType()->getName(), "{$label} language parameter must be a string");

                if ($callbackKey === 'install') {
                    $enable = $reflection->getParameters()[1];
                    $this->assertSame('bool', $enable->getType()->getName(), "{$label} enable parameter must be a bool");
                    $this->assertTrue($enable->isOptional(), "{$label} enable parameter must be optional");
                }

                $this->assertSame($callbackKey === 'derived' ? 'array' : 'bool', $reflection->getReturnType()->getName(), "{$label} return type");
            }
        }
    }

    public function testStrongsLanguagesAreThePresetLanguages()
    {
        $this->assertSame(array_keys(FeatureDefinitions::STRONGS_PRESETS), FeatureDefinitions::find('strongs')['languages']);
    }

    public function testStrongsIsOfferedInChineseFrenchAndPortuguese(): void
    {
        foreach(['zh' => 'cn_cbols', 'fr' => 'strong_fr', 'pt' => 'str_pt'] as $language => $module) {
            $this->assertContains($language, FeatureDefinitions::find('strongs')['languages']);
            $this->assertContains($module, FeatureDefinitions::STRONGS_PRESETS[$language]);
            $this->assertFileExists(base_path('content/strongs/strongs_' . $module . '.zip'), $module . ' has no module file');
        }
    }

    public function testLegacyStrongsIsASingleLanguageFeature()
    {
        $definition = FeatureDefinitions::find('strongs_legacy');

        $this->assertNotNull($definition);
        $this->assertSame(FeatureDefinitions::LANGUAGE_MODE_SINGLE, FeatureDefinitions::getLanguageMode($definition));
        $this->assertSame('strongs_legacy', \App\Models\Feature::buildCode('strongs_legacy', 'en', FeatureDefinitions::LANGUAGE_MODE_SINGLE));
    }

    public function testLanguagesModeNoneForNullOrEmptyValues()
    {
        $definitionNull = ['languages' => null];
        $definitionEmptyString = ['languages' => ''];
        $definitionFalse = ['languages' => false];
        $definitionEmptyArray = ['languages' => []];

        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_NONE, FeatureDefinitions::getLanguageMode($definitionNull));
        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_NONE, FeatureDefinitions::getLanguageMode($definitionEmptyString));
        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_NONE, FeatureDefinitions::getLanguageMode($definitionFalse));
        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_NONE, FeatureDefinitions::getLanguageMode($definitionEmptyArray));
    }

    public function testLanguagesModeSingleForStringValue()
    {
        $definition = ['languages' => 'en'];

        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_SINGLE, FeatureDefinitions::getLanguageMode($definition));
        $this->assertEquals(['en'], FeatureDefinitions::normalizeLanguages($definition));
    }

    public function testLanguagesModeMultiForArrayEvenWithOneElement()
    {
        $definition = ['languages' => ['ru']];

        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_MULTI, FeatureDefinitions::getLanguageMode($definition));
        $this->assertEquals(['ru'], FeatureDefinitions::normalizeLanguages($definition));
    }
}
