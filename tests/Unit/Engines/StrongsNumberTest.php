<?php

namespace Tests\Unit\Engines;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Engine;

/**
 * Engine::_normalizeStrongsNumber(), shared by the strongs and query actions.
 */
class StrongsNumberTest extends TestCase
{
    /**
     * No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the method
     * is deprecated in 8.5, which CI runs.
     */
    private function normalize(string $raw): string
    {
        $Engine = (new \ReflectionClass(Engine::class))->newInstanceWithoutConstructor();

        return (new \ReflectionMethod($Engine, '_normalizeStrongsNumber'))->invoke($Engine, $raw);
    }

    public static function numberProvider(): array
    {
        return [
            'already clean'  => ['H1234', 'H1234'],
            'lower case'     => ['g5719', 'G5719'],
            'padding zeros'  => ['H01234', 'H1234'],
            'lower + padded' => ['h0001', 'H1'],
            'zero'           => ['G0', 'G0'],
        ];
    }

    #[DataProvider('numberProvider')]
    public function testItNormalizesTheNumber(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->normalize($raw));
    }
}
