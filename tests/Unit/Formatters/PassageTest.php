<?php

namespace Tests\Unit\Formatters;

use PHPUnit\Framework\TestCase;
use App\Formatters\Passage;

/**
 * Covers the Passage formatter's non-search, no-passage path.
 *
 * Mirrors LiteTest: the two formatters differ only in the argument they hand to
 * Passage::toArray(), so the early-exit behaviour must match.
 */
class PassageTest extends TestCase
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
            (object) ['book' => 1, 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning'],
        ]];

        $formatter = new Passage($results, null, null, ['en'], $this->makeInput());

        $this->assertSame([], $formatter->format());
    }

    public function testFormatReturnsEmptyArrayForEmptyResults(): void
    {
        $formatter = new Passage([], null, null, ['en'], $this->makeInput());

        $this->assertSame([], $formatter->format());
    }

    public function testFormatClearsPassagesWhenMappingFails(): void
    {
        $formatter = new Passage([], [], null, ['en'], $this->makeInput());

        $formatter->format();

        $property = new \ReflectionProperty(\App\Formatters\FormatterAbstract::class, 'Passages');

        $this->assertSame([], $property->getValue($formatter));
    }
}
