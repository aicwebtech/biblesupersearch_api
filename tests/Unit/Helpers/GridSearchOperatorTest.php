<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;

/**
 * Helpers::_mapSearchOperator() translates the admin grid's operator codes into SQL operators
 * and, for the LIKE variants, decorates the value with the wildcards that give each one its
 * meaning.
 *
 * A wrong wildcard here silently changes what an admin search matches - "begins with" quietly
 * becoming "contains" - so each code is pinned individually. The mapper is pure.
 */
class GridSearchOperatorTest extends TestCase
{
    /**
     * @return array{0: ?string, 1: mixed, 2: mixed}
     */
    private function map(string $operator, string $value): array
    {
        $method = new \ReflectionMethod(Helpers::class, '_mapSearchOperator');

        return $method->invoke(null, $operator, $value);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function comparisonOperatorProvider(): array
    {
        return [
            'equals'                => ['eq', '='],
            'not equal'             => ['ne', '!='],
            'less than'             => ['lt', '<'],
            'less than or equal'    => ['le', '<='],
            'greater than'          => ['gt', '>'],
            'greater than or equal' => ['ge', '>='],
        ];
    }

    #[DataProvider('comparisonOperatorProvider')]
    public function testComparisonOperatorsMapDirectly(string $code, string $expected): void
    {
        [$op, $val] = $this->map($code, 'kjv');

        $this->assertSame($expected, $op);
        $this->assertSame('kjv', $val, 'comparison operators must not decorate the value');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function likeOperatorProvider(): array
    {
        return [
            'begins with'     => ['bw', 'LIKE', 'kjv%'],
            'does not begin'  => ['bn', 'NOT LIKE', 'kjv%'],
            'ends with'       => ['ew', 'LIKE', '%kjv'],
            'does not end'    => ['en', 'NOT LIKE', '%kjv'],
            'contains'        => ['cn', 'LIKE', '%kjv%'],
            'does not contain' => ['nc', 'NOT LIKE', '%kjv%'],
        ];
    }

    #[DataProvider('likeOperatorProvider')]
    public function testLikeOperatorsPlaceTheirWildcards(string $code, string $expectedOp, string $expectedVal): void
    {
        [$op, $val] = $this->map($code, 'kjv');

        $this->assertSame($expectedOp, $op);
        $this->assertSame($expectedVal, $val);
    }

    /**
     * An unrecognised code must yield no operator, so the caller skips the clause entirely
     * rather than building a query with a null operator.
     */
    public function testAnUnknownOperatorYieldsNothing(): void
    {
        [$op, $val] = $this->map('not_a_real_operator', 'kjv');

        $this->assertNull($op);
        $this->assertSame('kjv', $val);
    }

    public function testWildcardsAreAppliedToAnEmptyValue(): void
    {
        [$op, $val] = $this->map('cn', '');

        $this->assertSame('LIKE', $op);
        $this->assertSame('%%', $val);
    }

    /**
     * "begins with" and "ends with" must not be interchangeable - this is the pair most
     * likely to be transposed.
     */
    public function testBeginsAndEndsWithAreNotInterchangeable(): void
    {
        [, $beginsWith] = $this->map('bw', 'kjv');
        [, $endsWith]   = $this->map('ew', 'kjv');

        $this->assertNotSame($beginsWith, $endsWith);
        $this->assertStringEndsWith('%', $beginsWith);
        $this->assertStringStartsWith('%', $endsWith);
    }
}
