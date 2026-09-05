<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\ConfigManager;

class ConfigManagerTest extends TestCase
{
    #[DataProvider('getValueAttributeDataProvider')]
    public function testGetValueAttribute(mixed $value, string $type, mixed $expected): void
    {
        $this->assertEquals($expected, ConfigManager::getValueAttribute($value, $type));
    }

    public static function getValueAttributeDataProvider(): array
    {
        return [
            'int from string'      => ['42',           'int',    42],
            'int zero'             => ['0',            'int',    0],
            'int from int'         => [7,              'int',    7],
            'array from json'      => ['["a","b"]',    'array',  ['a', 'b']],
            'object from json'     => ['{"k":"v"}',    'object', (object)['k' => 'v']],
            'bool true'            => ['1',            'bool',   true],
            'bool false'           => ['',             'bool',   false],
            'string unchanged'     => ['hello',        'string', 'hello'],
            'string default type'  => ['world',        'xyz',    'world'],
        ];
    }

    #[DataProvider('setValueAttributeDataProvider')]
    public function testSetValueAttribute(mixed $value, string $type, mixed $expected): void
    {
        $this->assertEquals($expected, ConfigManager::setValueAttribute($value, $type));
    }

    public static function setValueAttributeDataProvider(): array
    {
        return [
            'int cast'             => [42,             'int',    42],
            'int from string'      => ['7',            'int',    7],
            'array to json'        => [['a', 'b'],     'array',  '["a","b"]'],
            'object to json'       => [(object)['k'=>'v'], 'object', '{"k":"v"}'],
            'bool true to 1'       => [true,           'bool',   1],
            'bool false to 0'      => [false,          'bool',   0],
            'string trimmed'       => ['  hello  ',    'string', 'hello'],
            'null string to null'  => [null,           'string', null],
        ];
    }

    /**
     * `mail.sendmail` is fed to the mail transport as a command line, so it must
     * never be writable by an HTTP request regardless of the form's readonly attribute.
     */
    #[DataProvider('httpImmutableKeysDataProvider')]
    public function testRejectHttpImmutableKeys(array $submitted, array $expected): void
    {
        $this->assertSame($expected, ConfigManager::rejectHttpImmutableKeys($submitted));
    }

    public static function httpImmutableKeysDataProvider(): array
    {
        return [
            'dotted key stripped'  => [['mail.sendmail' => '/tmp/evil'], []],
            'form key stripped'    => [['mail__sendmail' => '/tmp/evil'], []],
            'both styles stripped' => [
                ['mail.sendmail' => '/tmp/evil', 'mail__sendmail' => '/tmp/evil'],
                [],
            ],
            'other keys survive'   => [
                ['app__client_url' => 'http://example.com', 'mail__sendmail' => '/tmp/evil'],
                ['app__client_url' => 'http://example.com'],
            ],
            'unrelated mail keys survive' => [
                ['mail__host' => 'smtp.example.com', 'mail__port' => 587],
                ['mail__host' => 'smtp.example.com', 'mail__port' => 587],
            ],
            'empty set'            => [[], []],
        ];
    }

    public function testRejectHttpImmutableKeysPassesThroughNonArray(): void
    {
        $this->assertNull(ConfigManager::rejectHttpImmutableKeys(null));
    }
}
