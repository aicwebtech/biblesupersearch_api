<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use App\Models\Verses\VerseStandard;

/**
 * Covers VerseStandard::_buildSpecialSearchJoin(), which assembles the SQL join behind
 * proximity search (~b book, ~c chapter, ~l same-chapter, and the default N-verse window).
 *
 * It is pure string building - no connection is opened - so it belongs here rather than
 * alongside the query tests in Tests\Feature. The queries it feeds are exercised end to end
 * by Tests\Feature\Query\ProximitySearchTest.
 */
class VerseStandardTest extends TestCase
{
    /**
     * @param array<string, mixed> $parameters
     */
    private function buildJoin(string $operator, array $parameters = []): string
    {
        $method = new \ReflectionMethod(VerseStandard::class, '_buildSpecialSearchJoin');

        return $method->invoke(null, 'verses_kjv', 'a', $operator, 'b', $parameters, 'a.id != b.id');
    }

    public function testEveryJoinMatchesOnBookAndCarriesTheOnClause(): void
    {
        $join = $this->buildJoin('~b');

        $this->assertStringStartsWith('INNER JOIN verses_kjv AS a ON a.book = b.book', $join);
        $this->assertStringEndsWith(' AND a.id != b.id', $join);
    }

    /**
     * A book-scoped match needs nothing beyond the book join every operator already gets.
     */
    public function testBookOperatorAddsNoFurtherConstraint(): void
    {
        $join = $this->buildJoin('~b');

        $this->assertStringNotContainsString('chapter', $join);
        $this->assertStringNotContainsString('BETWEEN', $join);
    }

    public function testChapterOperatorConstrainsToTheSameChapter(): void
    {
        $join = $this->buildJoin('~c');

        $this->assertStringContainsString('AND a.chapter = b.chapter', $join);
        $this->assertStringNotContainsString('BETWEEN', $join);
    }

    public function testOperatorIsTrimmedBeforeItIsMatched(): void
    {
        $this->assertSame($this->buildJoin('~c'), $this->buildJoin('  ~c  '));
    }

    /**
     * The default window is five verses either side, applied to the row id.
     */
    public function testProximityOperatorDefaultsToAFiveVerseWindow(): void
    {
        $join = $this->buildJoin('~p');

        $this->assertStringContainsString('AND a.id BETWEEN b.id - 5 AND b.id + 5', $join);
    }

    public function testProximityWindowCanBeGivenInTheOperator(): void
    {
        $join = $this->buildJoin('~p(10)');

        $this->assertStringContainsString('AND a.id BETWEEN b.id - 10 AND b.id + 10', $join);
    }

    /**
     * An explicit window in the operator wins over the request-level default.
     */
    public function testOperatorWindowOverridesTheParameter(): void
    {
        $join = $this->buildJoin('~p(10)', ['proximity_limit' => 3]);

        $this->assertStringContainsString('b.id - 10 AND b.id + 10', $join);
    }

    public function testProximityWindowFallsBackToTheParameter(): void
    {
        $join = $this->buildJoin('~p', ['proximity_limit' => 3]);

        $this->assertStringContainsString('AND a.id BETWEEN b.id - 3 AND b.id + 3', $join);
    }

    public function testAnEmptyProximityLimitParameterFallsBackToTheDefault(): void
    {
        $join = $this->buildJoin('~p', ['proximity_limit' => 0]);

        $this->assertStringContainsString('b.id - 5 AND b.id + 5', $join);
    }

    /**
     * Psalms verses are numbered continuously across a book whose chapters are separate
     * poems, so an id window there would leak across psalms. The default operator therefore
     * pins book 19 to a single chapter while leaving other books free to span chapters.
     */
    public function testProximityKeepsPsalmsWithinOneChapter(): void
    {
        $join = $this->buildJoin('~p');

        $this->assertStringContainsString('AND (a.book != 19 OR a.chapter = b.chapter )', $join);
    }

    /**
     * ~l asks for a same-chapter window explicitly, so the Psalms carve-out is unnecessary.
     */
    public function testSameChapterOperatorConstrainsEveryBookToOneChapter(): void
    {
        $join = $this->buildJoin('~l');

        $this->assertStringContainsString('AND a.chapter = b.chapter', $join);
        $this->assertStringNotContainsString('a.book != 19', $join);
        $this->assertStringContainsString('BETWEEN', $join);
    }

    public function testSameChapterOperatorAcceptsAWindow(): void
    {
        $join = $this->buildJoin('~l(2)');

        $this->assertStringContainsString('AND a.chapter = b.chapter', $join);
        $this->assertStringContainsString('b.id - 2 AND b.id + 2', $join);
    }
}
