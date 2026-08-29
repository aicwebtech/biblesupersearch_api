<?php

namespace Tests\Feature;

use App\RenderManager;
use Tests\TestCase;

/**
 * RenderManager validates its request in the constructor - which formats were asked for,
 * which Bibles, and whether the combination is even expressible - before any rendering is
 * attempted.
 *
 * These tests only construct the manager and read the errors it accumulated. Nothing is
 * rendered and no file is written; the render paths are covered by
 * Tests\Feature\RenderManagerTest.
 *
 * Note: $modules and $format must be arrays (or the strings 'ALL'/'OFFICIAL'), because the
 * constructor calls count() on them directly - a bare string would be a TypeError on PHP 8.
 */
class RenderManagerConstructionTest extends TestCase
{
    public function testAnUnknownFormatIsRejected(): void
    {
        $manager = new RenderManager(['kjv'], ['not_a_format']);

        $this->assertTrue($manager->hasErrors());
        $this->assertStringContainsString('not_a_format', implode(' ', $manager->getErrors()));
    }

    /**
     * Only the unknown format is rejected; a valid one alongside it still registers.
     */
    public function testAKnownFormatSurvivesAlongsideAnUnknownOne(): void
    {
        $manager = new RenderManager(['kjv'], ['not_a_format', 'text']);

        $property = new \ReflectionProperty(RenderManager::class, 'format');

        $this->assertSame(['text'], $property->getValue($manager));
    }

    public function testFormatAllExpandsToEveryRegisteredFormat(): void
    {
        $manager = new RenderManager(['kjv'], 'ALL');

        $property = new \ReflectionProperty(RenderManager::class, 'format');

        $this->assertSame(array_keys(RenderManager::$register), $property->getValue($manager));
    }

    /**
     * Many Bibles in many formats has no single sensible output, so it is refused outright
     * rather than producing a partial download.
     */
    public function testManyBiblesInManyFormatsIsRefused(): void
    {
        $manager = new RenderManager('ALL', 'ALL');

        $this->assertTrue($manager->hasErrors());
        $this->assertStringContainsString(
            'Cannot request multiple items for both Bible and format',
            implode(' ', $manager->getErrors())
        );
    }

    /**
     * The refusal happens before any Bible is looked up, so no further errors pile on.
     */
    public function testTheImpossibleCombinationShortCircuitsBeforeSelectingBibles(): void
    {
        $manager = new RenderManager('ALL', 'ALL');

        $property = new \ReflectionProperty(RenderManager::class, 'Bibles');

        $this->assertSame([], $property->getValue($manager));
    }

    public function testAnUnknownModuleIsReported(): void
    {
        $manager = new RenderManager(['no_such_module'], ['text']);

        $this->assertTrue($manager->hasErrors());
        $this->assertNotEmpty($manager->getErrors());
    }

    /**
     * Requesting one Bible in one format is the ordinary case and must not be treated as a
     * multi-item request.
     */
    public function testASingleBibleInASingleFormatIsNotAMultiRequest(): void
    {
        $manager = new RenderManager(['kjv'], ['text']);

        $multiBibles = new \ReflectionProperty(RenderManager::class, 'multi_bibles');
        $multiFormat = new \ReflectionProperty(RenderManager::class, 'multi_format');

        $this->assertFalse($multiBibles->getValue($manager));
        $this->assertFalse($multiFormat->getValue($manager));
    }

    public function testSeveralFormatsMakeItAMultiFormatRequest(): void
    {
        $manager = new RenderManager(['kjv'], ['text', 'csv']);

        $property = new \ReflectionProperty(RenderManager::class, 'multi_format');

        $this->assertTrue($property->getValue($manager));
    }

    /**
     * Asking for many Bibles in many formats is what forces a zip, since the result cannot be
     * a single file.
     */
    public function testSeveralBiblesInOneFormatCanBeZipped(): void
    {
        $manager = new RenderManager(['kjv', 'ueb2'], ['text'], true);

        $property = new \ReflectionProperty(RenderManager::class, 'zip');

        $this->assertTrue($property->getValue($manager));
    }

    public function testRenderClassResolvesForASingleFormat(): void
    {
        $manager = new RenderManager(['kjv'], ['text']);

        $this->assertSame(\App\Renderers\PlainText::class, $manager->getRenderClass());
    }
}
