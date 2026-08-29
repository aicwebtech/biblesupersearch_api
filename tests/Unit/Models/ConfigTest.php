<?php

namespace Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Models\Config;

/**
 * The Config model stores every setting's value in one text column and casts it on the way
 * in and out according to the row's own `type`. Both accessors delegate to ConfigManager,
 * which is pure, so no database is involved.
 */
class ConfigTest extends TestCase
{
    private function config(string $type, array $rawAttributes = []): Config
    {
        $config = new Config();
        $config->setRawAttributes($rawAttributes);
        $config->type = $type;

        return $config;
    }

    public function testTimestampsAreDisabled(): void
    {
        $this->assertFalse((new Config())->timestamps);
    }

    /**
     * @return array<string, array{string, string, mixed}>
     */
    public static function readCastProvider(): array
    {
        return [
            'int'          => ['int', '42', 42],
            'int from text' => ['int', 'not a number', 0],
            'bool true'    => ['bool', '1', true],
            'bool false'   => ['bool', '0', false],
            'string'       => ['string', 'hello', 'hello'],
            'unknown type' => ['something_else', 'hello', 'hello'],
        ];
    }

    #[DataProvider('readCastProvider')]
    public function testDefaultAttributeIsCastOnRead(string $type, string $stored, mixed $expected): void
    {
        $config = $this->config($type, ['default' => $stored]);

        $this->assertSame($expected, $config->default);
    }

    public function testArrayDefaultIsDecodedOnRead(): void
    {
        $config = $this->config('array', ['default' => '{"a":1}']);

        $this->assertSame(['a' => 1], $config->default);
    }

    public function testObjectDefaultIsDecodedOnRead(): void
    {
        $config = $this->config('object', ['default' => '{"a":1}']);

        $this->assertSame(1, $config->default->a);
    }

    public function testIntDefaultIsCastOnWrite(): void
    {
        $config = $this->config('int');
        $config->default = '42';

        $this->assertSame(42, $config->getAttributes()['default']);
    }

    public function testArrayDefaultIsEncodedOnWrite(): void
    {
        $config = $this->config('array');
        $config->default = ['a' => 1];

        $this->assertSame('{"a":1}', $config->getAttributes()['default']);
    }

    public function testBoolDefaultIsStoredAsAnInteger(): void
    {
        $config = $this->config('bool');
        $config->default = true;

        $this->assertSame(1, $config->getAttributes()['default']);
    }

    /**
     * Untyped values are trimmed, and an empty one is normalised to null rather than an
     * empty string, so "unset" is a single representation in the column.
     */
    public function testStringDefaultIsTrimmedOnWrite(): void
    {
        $config = $this->config('string');
        $config->default = '  padded  ';

        $this->assertSame('padded', $config->getAttributes()['default']);
    }

    public function testEmptyStringDefaultBecomesNullOnWrite(): void
    {
        $config = $this->config('string');
        $config->default = '';

        $this->assertNull($config->getAttributes()['default']);
    }
}
