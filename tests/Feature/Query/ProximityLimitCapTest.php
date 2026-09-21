<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use App\Engine;

/**
 * proximity_limit is interpolated into a self-join range (BETWEEN id-N AND
 * id+N) rather than bound, so an unbounded N makes an arbitrarily expensive
 * query. It is clamped at input validation and again in the query builder.
 */
class ProximityLimitCapTest extends TestCase
{
    protected function sanitize(array $input)
    {
        $Engine = new Engine();
        $method = new \ReflectionMethod(Engine::class, '_sanitizeInput');

        return $method->invoke($Engine, $input, [
            'proximity_limit' => [
                'type' => 'int_bounded',
                'max'  => config('bss.proximity_limit_max', 100),
            ],
        ]);
    }

    public function testOversizedProximityLimitIsClamped(): void
    {
        $clean = $this->sanitize(['proximity_limit' => 2147483647]);

        $this->assertSame(100, $clean['proximity_limit']);
    }

    public function testOrdinaryProximityLimitPassesThrough(): void
    {
        $clean = $this->sanitize(['proximity_limit' => 7]);

        $this->assertSame(7, $clean['proximity_limit']);
    }

    public function testValueAtCeilingIsKept(): void
    {
        $clean = $this->sanitize(['proximity_limit' => 100]);

        $this->assertSame(100, $clean['proximity_limit']);
    }

    /**
     * The builder clamps independently of the caller, so a distance supplied
     * through the PROX(n) operator cannot exceed the ceiling either.
     */
    public function testQueryBuilderClampsOversizedOperatorDistance(): void
    {
        $max = (int) config('bss.proximity_limit_max', 100);

        $sql = $this->buildJoin('~(' . ($max + 5000) . ')');

        $this->assertStringContainsString('- ' . $max . ' AND', $sql);
        $this->assertStringNotContainsString((string) ($max + 5000), $sql);
    }

    public function testQueryBuilderClampsOversizedParameter(): void
    {
        $max = (int) config('bss.proximity_limit_max', 100);

        $sql = $this->buildJoin('~', ['proximity_limit' => 999999]);

        $this->assertStringContainsString('- ' . $max . ' AND', $sql);
        $this->assertStringNotContainsString('999999', $sql);
    }

    /**
     * _buildSpecialSearchJoin is protected; exercise it directly.
     *
     * No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the method is
     * deprecated in 8.5, which CI runs.
     */
    protected function buildJoin(string $operator, array $parameters = []): string
    {
        $method = new \ReflectionMethod(\App\Models\Verses\VerseStandard::class, '_buildSpecialSearchJoin');

        // static: ($table, $alias, $operator, $alias2, $parameters, $on_clause)
        return (string) $method->invoke(null, 'verses', 'a', $operator, 'b', $parameters, 'a.id = b.id');
    }
}
