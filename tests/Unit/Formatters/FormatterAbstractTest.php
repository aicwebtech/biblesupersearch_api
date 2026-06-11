<?php

namespace Tests\Unit\Formatters;

use PHPUnit\Framework\TestCase;
use App\Formatters\Minimal;
use App\Formatters\FormatterAbstract;

class FormatterAbstractTest extends TestCase
{
    private function makeInput(array $overrides = []): array
    {
        return array_merge(['group_passage_search_results' => false], $overrides);
    }

    // -----------------------------------------------------------------------
    // Minimal formatter (passes results through _preFormatVerses unchanged)
    // -----------------------------------------------------------------------

    public function testMinimalFormatReturnsResultsUnchanged(): void
    {
        $results = ['kjv' => [
            (object)['book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => 'For God so loved the world'],
        ]];

        $formatter = new Minimal($results, null, null, ['en'], $this->makeInput());
        $this->assertSame($results, $formatter->format());
    }

    public function testMinimalFormatWithEmptyResults(): void
    {
        $formatter = new Minimal([], null, null, ['en'], $this->makeInput());
        $this->assertSame([], $formatter->format());
    }

    public function testMinimalFormatWithMultipleBibles(): void
    {
        $results = [
            'kjv'     => [(object)['book' => 1, 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning']],
            'bishops' => [(object)['book' => 1, 'chapter' => 1, 'verse' => 1, 'text' => 'In the begynninge']],
        ];

        $formatter = new Minimal($results, null, null, ['en'], $this->makeInput());
        $this->assertSame($results, $formatter->format());
        $this->assertCount(2, $formatter->format());
    }

    // -----------------------------------------------------------------------
    // FormatterAbstract constructor wires internal state
    // -----------------------------------------------------------------------

    public function testConstructorStoresResultsAndMetadata(): void
    {
        $results  = ['kjv' => []];
        $passages = [new \App\Passage()];
        $input    = $this->makeInput();

        // Use Minimal as a concrete proxy to inspect inherited state
        $formatter = new Minimal($results, $passages, null, ['en'], $input);

        // Verify is_search is false (Search is null)
        $rf = new \ReflectionProperty(FormatterAbstract::class, 'is_search');
        $this->assertFalse($rf->getValue($formatter));

        // Verify has_passages is true (passages array is non-empty)
        $rf2 = new \ReflectionProperty(FormatterAbstract::class, 'has_passages');
        $this->assertTrue($rf2->getValue($formatter));
    }

    public function testConstructorHasPassagesFalseWhenPassagesEmpty(): void
    {
        $formatter = new Minimal([], [], null, ['en'], $this->makeInput());

        $rf = new \ReflectionProperty(FormatterAbstract::class, 'has_passages');
        $this->assertFalse($rf->getValue($formatter));
    }
}
