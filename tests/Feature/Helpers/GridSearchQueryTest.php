<?php

namespace Tests\Feature\Helpers;

use App\Helpers;
use App\Models\Language;
use Tests\TestCase;

/**
 * Helpers::buildGridSearchQuery() and buildGridSearchMuiltiQuery() turn the admin grid's
 * search payload into where clauses on an Eloquent builder.
 *
 * These only build queries - nothing is executed against content data - but they need a real
 * builder and connection to render SQL, so they are feature tests.
 */
class GridSearchQueryTest extends TestCase
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $fieldMap
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array<string, mixed>}
     */
    private function build(array $data, array $fieldMap = []): array
    {
        $query = Language::query();

        Helpers::buildGridSearchQuery($data, $query, $fieldMap);

        return [$query, $data];
    }

    public function testASingleSearchAddsAWhereClause(): void
    {
        [$query] = $this->build([
            'searchField'  => 'code',
            'searchOper'   => 'eq',
            'searchString' => 'en',
        ]);

        $this->assertStringContainsString('"code" = ?', str_replace('`', '"', $query->toSql()));
        $this->assertSame(['en'], $query->getBindings());
    }

    public function testAContainsSearchBindsAWildcardedValue(): void
    {
        [$query] = $this->build([
            'searchField'  => 'name',
            'searchOper'   => 'cn',
            'searchString' => 'engl',
        ]);

        $this->assertStringContainsStringIgnoringCase('like', $query->toSql());
        $this->assertSame(['%engl%'], $query->getBindings());
    }

    /**
     * The field map lets the grid's column name differ from the database column, which is how
     * joined columns are addressed.
     */
    public function testTheFieldMapRenamesTheColumn(): void
    {
        [$query] = $this->build(
            ['searchField' => 'language', 'searchOper' => 'eq', 'searchString' => 'en'],
            ['language' => 'languages.code']
        );

        $this->assertStringContainsString('code', $query->toSql());
        $this->assertSame(['en'], $query->getBindings());
    }

    /**
     * A field mapped to POSTFILTER is not expressible in SQL, so it is diverted into
     * _post_filters for the caller to apply after the rows come back - and must not leak into
     * the query as a column named "POSTFILTER".
     */
    public function testAPostFilterFieldIsDivertedRatherThanQueried(): void
    {
        [$query, $data] = $this->build(
            ['searchField' => 'computed', 'searchOper' => 'eq', 'searchString' => 'yes'],
            ['computed' => 'POSTFILTER']
        );

        $this->assertSame(['computed' => 'yes'], $data['_post_filters']);
        $this->assertStringNotContainsString('POSTFILTER', $query->toSql());
        $this->assertSame([], $query->getBindings());
    }

    /**
     * _no_rest_ is the grid's "no restriction" sentinel: it must add no clause at all.
     */
    public function testTheNoRestrictionSentinelAddsNoClause(): void
    {
        [$query] = $this->build([
            'searchField'  => 'code',
            'searchOper'   => 'eq',
            'searchString' => '_no_rest_',
        ]);

        $this->assertSame([], $query->getBindings());
    }

    public function testAnUnknownOperatorAddsNoClause(): void
    {
        [$query] = $this->build([
            'searchField'  => 'code',
            'searchOper'   => 'not_real',
            'searchString' => 'en',
        ]);

        $this->assertSame([], $query->getBindings());
    }

    public function testPostFiltersAreInitialisedEvenWhenUnused(): void
    {
        [, $data] = $this->build([
            'searchField'  => 'code',
            'searchOper'   => 'eq',
            'searchString' => 'en',
        ]);

        $this->assertSame([], $data['_post_filters']);
    }

    // -----------------------------------------------------------------------
    // Multi-rule filters
    // -----------------------------------------------------------------------

    /**
     * @param array<int, array<string, string>> $rules
     * @return array{0: \Illuminate\Database\Eloquent\Builder, 1: array<string, mixed>}
     */
    private function buildMulti(array $rules, string $groupOp, array $fieldMap = []): array
    {
        $data = [
            'searchField'  => '',
            'searchOper'   => '',
            'searchString' => '',
            'filters'      => json_encode(['groupOp' => $groupOp, 'rules' => $rules]),
        ];

        $query = Language::query();

        Helpers::buildGridSearchQuery($data, $query, $fieldMap);

        return [$query, $data];
    }

    /**
     * A present filters payload takes over from the single-field search entirely.
     */
    public function testMultipleRulesAreGroupedWithAnd(): void
    {
        [$query] = $this->buildMulti([
            ['field' => 'code', 'op' => 'eq', 'data' => 'en'],
            ['field' => 'name', 'op' => 'cn', 'data' => 'engl'],
        ], 'AND');

        $this->assertSame(['en', '%engl%'], $query->getBindings());
        $this->assertStringContainsString('and', strtolower($query->toSql()));
    }

    public function testMultipleRulesCanBeGroupedWithOr(): void
    {
        [$query] = $this->buildMulti([
            ['field' => 'code', 'op' => 'eq', 'data' => 'en'],
            ['field' => 'code', 'op' => 'eq', 'data' => 'de'],
        ], 'OR');

        $this->assertSame(['en', 'de'], $query->getBindings());
        $this->assertStringContainsString('or', strtolower($query->toSql()));
    }

    public function testARuleUsingTheNoRestrictionSentinelIsSkipped(): void
    {
        [$query] = $this->buildMulti([
            ['field' => 'code', 'op' => 'eq', 'data' => '_no_rest_'],
            ['field' => 'name', 'op' => 'eq', 'data' => 'English'],
        ], 'AND');

        $this->assertSame(['English'], $query->getBindings());
    }

    public function testAPostFilterRuleIsDivertedFromAMultiFilter(): void
    {
        [$query, $data] = $this->buildMulti(
            [
                ['field' => 'code', 'op' => 'eq', 'data' => 'en'],
                ['field' => 'computed', 'op' => 'eq', 'data' => 'yes'],
            ],
            'AND',
            ['computed' => 'POSTFILTER']
        );

        $this->assertSame(['computed' => 'yes'], $data['_post_filters']);
        $this->assertSame(['en'], $query->getBindings());
    }

    /**
     * An empty filters value falls through to the single-field path rather than producing an
     * empty group.
     */
    public function testAnEmptyFiltersPayloadFallsBackToTheSingleFieldSearch(): void
    {
        $data = [
            'searchField'  => 'code',
            'searchOper'   => 'eq',
            'searchString' => 'en',
            'filters'      => '',
        ];

        $query = Language::query();

        Helpers::buildGridSearchQuery($data, $query, []);

        $this->assertSame(['en'], $query->getBindings());
    }
}
