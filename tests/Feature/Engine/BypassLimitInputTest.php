<?php

namespace Tests\Feature\Engine;

use Tests\TestCase;
use App\Engine;

/**
 * bypass_limit is asked for by the client but granted only to an administrator, and the
 * raw request array reaches the helper -- so bypass_limit[]=1 arrives as an array.
 *
 * filter_var() returns FALSE for an array rather than raising, on every supported PHP
 * version (8.2-8.5, checked), so the request is correctly denied. These tests pin that
 * behaviour: if a future PHP turns it into a TypeError, the helper would 500 the
 * render/download endpoint instead of denying, and this is where that would surface.
 */
class BypassLimitInputTest extends TestCase
{
    /**
     * @param  mixed  $value
     * @return bool
     */
    protected function requested($value): bool
    {
        $Engine = new Engine();
        $method = new \ReflectionMethod(Engine::class, '_bypassRenderLimitRequested');

        return (bool) $method->invoke($Engine, ['bypass_limit' => $value]);
    }

    /**
     * @param  mixed  $value
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deniedValueProvider')]
    public function testMalformedOrNegativeValuesAreDenied($value): void
    {
        $this->assertFalse($this->requested($value), 'Bypass must not be granted');
    }

    public static function deniedValueProvider(): array
    {
        return [
            'array'        => [['1']],
            'nested array' => [[['1']]],
            'empty array'  => [[]],
            'object'       => [new \stdClass],
            'string false' => ['false'],
            'string zero'  => ['0'],
            'empty string' => [''],
            'null'         => [null],
            'zero'         => [0],
        ];
    }

    /**
     * Absent means absent, and must not reach filter_var() at all.
     */
    public function testAnAbsentKeyIsDenied(): void
    {
        $Engine = new Engine();
        $method = new \ReflectionMethod(Engine::class, '_bypassRenderLimitRequested');

        $this->assertFalse((bool) $method->invoke($Engine, []));
    }

    /**
     * A truthy request is still only a request: the Gate decides, and these tests run
     * unauthenticated, so the answer is still no.
     */
    public function testATruthyRequestStillRequiresAuthorisation(): void
    {
        $this->assertFalse($this->requested('1'), 'An unauthenticated caller must be denied');
        $this->assertFalse($this->requested(TRUE), 'An unauthenticated caller must be denied');
    }
}
