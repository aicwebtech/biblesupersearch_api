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
        $this->assertEquals("Strong's Definitions", $definition['name']);
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

    public function testInstallAndUninstallCallbacksMatchContractWithoutExecution()
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
