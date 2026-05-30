<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Features\FeatureDefinitions;

class FeatureDefinitionsTest extends TestCase
{
    public function test_all_returns_expected_features()
    {
        $definitions = FeatureDefinitions::all();

        $this->assertIsArray($definitions);
        $this->assertGreaterThanOrEqual(2, count($definitions));

        $identifiers = array_column($definitions, 'identifier');
        $this->assertContains('cross_references', $identifiers);
        $this->assertContains('strongs', $identifiers);
    }

    public function test_all_returns_cross_references_with_null_language()
    {
        $definitions = FeatureDefinitions::all();
        $crossRef = collect($definitions)->firstWhere('identifier', 'cross_references');

        $this->assertNotNull($crossRef);
        $this->assertNull($crossRef['languages']);
        $this->assertEquals('Cross References', $crossRef['name']);
    }

    public function test_all_returns_strongs_with_multiple_languages()
    {
        $definitions = FeatureDefinitions::all();
        $strongs = collect($definitions)->firstWhere('identifier', 'strongs');

        $this->assertNotNull($strongs);
        $this->assertIsArray($strongs['languages']);
        $this->assertContains('en', $strongs['languages']);
        $this->assertContains('ru', $strongs['languages']);
        $this->assertContains('es', $strongs['languages']);
    }

    public function test_find_returns_correct_definition()
    {
        $definition = FeatureDefinitions::find('strongs');

        $this->assertNotNull($definition);
        $this->assertEquals('strongs', $definition['identifier']);
        $this->assertEquals("Strong's Definitions", $definition['name']);
    }

    public function test_find_returns_null_for_unknown()
    {
        $definition = FeatureDefinitions::find('nonexistent_feature');

        $this->assertNull($definition);
    }

    public function test_definitions_have_required_fields()
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

    public function test_install_and_uninstall_callbacks_are_callable()
    {
        $definitions = FeatureDefinitions::all();

        foreach ($definitions as $definition) {
            $this->assertTrue(is_callable($definition['install']));
            $this->assertTrue(is_callable($definition['uninstall']));
        }
    }

    public function test_install_and_uninstall_callbacks_match_contract_without_execution()
    {
        $definitions = FeatureDefinitions::all();
    
        foreach ($definitions as $definition) {
            foreach (['install', 'uninstall'] as $callbackKey) {
                $callback = $definition[$callbackKey];
    
                $this->assertIsCallable($callback);
    
                $reflection = new \ReflectionFunction(\Closure::fromCallable($callback));
    
                $this->assertSame(
                    1,
                    $reflection->getNumberOfParameters(),
                    "{$definition['identifier']} {$callbackKey} callback must accept exactly one parameter"
                );
    
                $parameter = $reflection->getParameters()[0];
                $this->assertTrue(
                    $parameter->allowsNull(),
                    "{$definition['identifier']} {$callbackKey} callback parameter must allow null"
                );
    
                $parameterType = $parameter->getType();
                $this->assertInstanceOf(\ReflectionNamedType::class, $parameterType);
                $this->assertSame('string', $parameterType->getName());
    
                $returnType = $reflection->getReturnType();
                $this->assertInstanceOf(\ReflectionNamedType::class, $returnType);
                $this->assertSame('bool', $returnType->getName());
            }
        }
    }

    public function test_languages_mode_none_for_null_or_empty_values()
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

    public function test_languages_mode_single_for_string_value()
    {
        $definition = ['languages' => 'en'];

        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_SINGLE, FeatureDefinitions::getLanguageMode($definition));
        $this->assertEquals(['en'], FeatureDefinitions::normalizeLanguages($definition));
    }

    public function test_languages_mode_multi_for_array_even_with_one_element()
    {
        $definition = ['languages' => ['ru']];

        $this->assertEquals(FeatureDefinitions::LANGUAGE_MODE_MULTI, FeatureDefinitions::getLanguageMode($definition));
        $this->assertEquals(['ru'], FeatureDefinitions::normalizeLanguages($definition));
    }
}
