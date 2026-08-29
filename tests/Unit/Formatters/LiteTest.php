<?php

namespace Tests\Unit\Formatters;

use PHPUnit\Framework\TestCase;
use App\Formatters\Lite;

/**
 * Covers the Lite formatter's non-search, no-passage path.
 *
 * With no Search and no Passages, _mapResultsToPassages() bails out before it touches
 * App\Passage, so format() resolves entirely in memory - no database, no application.
 */
class LiteTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeInput(array $overrides = []): array
    {
        return array_merge(['group_passage_search_results' => false], $overrides);
    }

    public function testFormatReturnsEmptyArrayWhenThereIsNoSearchAndNoPassages(): void
    {
        $results = ['kjv' => [
            (object) ['book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => 'For God so loved the world'],
        ]];

        $formatter = new Lite($results, null, null, ['en'], $this->makeInput());

        $this->assertSame([], $formatter->format());
    }

    public function testFormatReturnsEmptyArrayForEmptyResults(): void
    {
        $formatter = new Lite([], null, null, ['en'], $this->makeInput());

        $this->assertSame([], $formatter->format());
    }

    /**
     * The failed mapping must clear Passages rather than leave the originals in place,
     * otherwise the formatter would emit passages it never managed to claim verses for.
     */
    public function testFormatClearsPassagesWhenMappingFails(): void
    {
        $formatter = new Lite([], [], null, ['en'], $this->makeInput());

        $formatter->format();

        $property = new \ReflectionProperty(\App\Formatters\FormatterAbstract::class, 'Passages');

        $this->assertSame([], $property->getValue($formatter));
    }

    public function testFormatLeavesOriginalPassagesUntouched(): void
    {
        $formatter = new Lite([], [], null, ['en'], $this->makeInput());

        $formatter->format();

        $property = new \ReflectionProperty(\App\Formatters\FormatterAbstract::class, 'PassagesOrig');

        $this->assertSame([], $property->getValue($formatter));
    }
}
