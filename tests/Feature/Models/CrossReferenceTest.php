<?php

namespace Tests\Feature\Models;

use App\Models\CrossReference;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class CrossReferenceTest extends TestCase
{
    /**
     * Creates in-memory CrossReference model instance with given attributes, bypassing mass assignment and other protections
     * Does not write to database, but allows testing of model methods that require a model instance with specific attributes
     * @param array<string, mixed> $attributes
     */
    protected function makeCrossReference(array $attributes): CrossReference
    {
        $CrossReference = new CrossReference();
        $CrossReference->setRawAttributes($attributes, true);

        return $CrossReference;
    }

    public function testGroupedForSourceVersesReturnsMatchingRowsInOrder(): void
    {

        $crossReferences = new Collection([
            $this->makeCrossReference([
                'from_book' => 43,
                'from_chapter' => 3,
                'from_verse' => 16,
                'to_book' => 45,
                'to_chapter_start' => 3,
                'to_verse_start' => 23,
                'to_chapter_end' => 3,
                'to_verse_end' => 24,
                'votes' => 12,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            $this->makeCrossReference([
                'from_book' => 43,
                'from_chapter' => 3,
                'from_verse' => 16,
                'to_book' => 45,
                'to_chapter_start' => 5,
                'to_verse_start' => 1,
                'to_chapter_end' => 5,
                'to_verse_end' => 2,
                'votes' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            $this->makeCrossReference([
                'from_book' => 43,
                'from_chapter' => 3,
                'from_verse' => 17,
                'to_book' => 45,
                'to_chapter_start' => 8,
                'to_verse_start' => 28,
                'to_chapter_end' => 8,
                'to_verse_end' => 29,
                'votes' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
        ]);

        $grouped = CrossReference::groupBySourceVerses($crossReferences);

        $this->assertCount(2, $grouped);
        $this->assertSame(16, $grouped[0]['from_verse']);
        $this->assertSame(2, count($grouped[0]['cross_references']));
        $this->assertArrayNotHasKey('created_at', $grouped[0]);
        $this->assertArrayNotHasKey('updated_at', $grouped[0]);
        $this->assertArrayNotHasKey('created_at', $grouped[0]['cross_references'][0]);
        $this->assertArrayNotHasKey('updated_at', $grouped[0]['cross_references'][0]);
        $this->assertSame(17, $grouped[1]['from_verse']);
    }
}