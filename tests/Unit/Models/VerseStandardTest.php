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

    /**
     * Reads the ceiling from the class rather than restating it, so raising or lowering the
     * property does not silently strand these tests on a stale number.
     */
    private function proximityLimitMax(): int
    {
        $property = new \ReflectionProperty(VerseStandard::class, 'proximity_limit_max');

        return $property->getValue();
    }

    /**
     * The window widens the BETWEEN range on every arm of an N-way self join, so an
     * arbitrarily large request is an unauthenticated way to burn CPU and memory.
     */
    public function testAnOversizedProximityLimitParameterIsClampedToTheMaximum(): void
    {
        $max  = $this->proximityLimitMax();
        $join = $this->buildJoin('~p', ['proximity_limit' => 100000000]);

        $this->assertStringContainsString('AND a.id BETWEEN b.id - ' . $max . ' AND b.id + ' . $max, $join);
        $this->assertStringNotContainsString('100000000', $join);
    }

    /**
     * The inline operator form reaches the same window, so it needs the same ceiling.
     */
    public function testAnOversizedWindowInTheOperatorIsClampedToTheMaximum(): void
    {
        $max  = $this->proximityLimitMax();
        $join = $this->buildJoin('~p(100000000)');

        $this->assertStringContainsString('AND a.id BETWEEN b.id - ' . $max . ' AND b.id + ' . $max, $join);
        $this->assertStringNotContainsString('100000000', $join);
    }

    /**
     * A negative window would invert the BETWEEN range and silently match nothing.
     */
    public function testANegativeProximityLimitIsRaisedToTheFloor(): void
    {
        $join = $this->buildJoin('~p', ['proximity_limit' => -20]);

        $this->assertStringContainsString('AND a.id BETWEEN b.id - 0 AND b.id + 0', $join);
        $this->assertStringNotContainsString('- -20', $join);
    }

    /**
     * PROX(0) asks for both keywords in the one verse - the join's ON clause is the sub-search's
     * own WHERE rather than an exclusion, so a zero window is a search a user can meaningfully
     * write. Raising it to one would silently widen the result to the neighbouring verses.
     */
    public function testAZeroWindowInTheOperatorIsPreserved(): void
    {
        $join = $this->buildJoin('~p(0)');

        $this->assertStringContainsString('AND a.id BETWEEN b.id - 0 AND b.id + 0', $join);
        $this->assertStringNotContainsString('b.id - 1', $join);
    }

    /**
     * A non-numeric parameter must not reach the SQL, whatever a caller of the public
     * getSearch() entry points passes in its $parameters array.
     */
    public function testANonNumericProximityLimitIsCastBeforeItReachesTheSql(): void
    {
        $join = $this->buildJoin('~p', ['proximity_limit' => '7 OR 1=1']);

        $this->assertStringContainsString('AND a.id BETWEEN b.id - 7 AND b.id + 7', $join);
        $this->assertStringNotContainsString('1=1', $join);
    }

    public function testAProximityLimitAtTheMaximumIsLeftAlone(): void
    {
        $max  = $this->proximityLimitMax();
        $join = $this->buildJoin('~p', ['proximity_limit' => $max]);

        $this->assertStringContainsString('AND a.id BETWEEN b.id - ' . $max . ' AND b.id + ' . $max, $join);
    }
}
