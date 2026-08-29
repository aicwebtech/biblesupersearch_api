<?php

namespace Tests\Feature\Formatters;

use App\Formatters\Simple;
use Tests\TestCase;

/**
 * The Simple formatter decorates each verse with its book name and short name, looked up
 * from the installed book list. That lookup needs the database, so this is a feature test;
 * it only reads the installed book data.
 */
class SimpleTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeInput(array $overrides = []): array
    {
        return array_merge(['group_passage_search_results' => false], $overrides);
    }

    /**
     * @return array<string, array<int, object>>
     */
    private function johnThreeSixteen(): array
    {
        return ['kjv' => [
            (object) ['book' => 43, 'chapter' => 3, 'verse' => 16, 'text' => 'For God so loved the world'],
        ]];
    }

    public function testFormatAddsBookNameAndShortnameToEachVerse(): void
    {
        $formatter = new Simple($this->johnThreeSixteen(), null, null, ['en'], $this->makeInput());

        $formatted = $formatter->format();
        $verse     = $formatted['kjv'][0];

        $this->assertSame('John', $verse->book_name);
        $this->assertNotEmpty($verse->book_shortname);
    }

    public function testFormatPreservesTheOriginalVerseFields(): void
    {
        $formatter = new Simple($this->johnThreeSixteen(), null, null, ['en'], $this->makeInput());

        $verse = $formatter->format()['kjv'][0];

        $this->assertSame(43, $verse->book);
        $this->assertSame(3, $verse->chapter);
        $this->assertSame(16, $verse->verse);
        $this->assertSame('For God so loved the world', $verse->text);
    }

    /**
     * With no language given the formatter falls back to config('bss.defaults.language_short'),
     * which is the branch the API takes whenever the caller omits a language.
     */
    public function testFormatFallsBackToTheDefaultLanguageWhenNoneGiven(): void
    {
        $formatter = new Simple($this->johnThreeSixteen(), null, null, [], $this->makeInput());

        $this->assertSame('John', $formatter->format()['kjv'][0]->book_name);
    }

    public function testFormatDecoratesEveryBibleInTheResults(): void
    {
        $results = [
            'kjv'     => [(object) ['book' => 1, 'chapter' => 1, 'verse' => 1, 'text' => 'In the beginning']],
            'bishops' => [(object) ['book' => 1, 'chapter' => 1, 'verse' => 1, 'text' => 'In the begynninge']],
        ];

        $formatter = new Simple($results, null, null, ['en'], $this->makeInput());
        $formatted = $formatter->format();

        $this->assertSame('Genesis', $formatted['kjv'][0]->book_name);
        $this->assertSame('Genesis', $formatted['bishops'][0]->book_name);
    }

    public function testFormatReturnsEmptyResultsUnchanged(): void
    {
        $formatter = new Simple([], null, null, ['en'], $this->makeInput());

        $this->assertSame([], $formatter->format());
    }
}
