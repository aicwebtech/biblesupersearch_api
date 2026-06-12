<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\ApiAccessManager;

class ApiAccessManagerTest extends TestCase
{
    #[DataProvider('parseDomainDataProvider')]
    public function testParseDomain(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, ApiAccessManager::parseDomain($input));
    }

    public static function parseDomainDataProvider(): array
    {
        return [
            'bare domain'             => ['example.com',                   'example.com'],
            'www prefix stripped'     => ['www.example.com',               'example.com'],
            'http scheme stripped'    => ['http://example.com',            'example.com'],
            'https scheme stripped'   => ['https://example.com',           'example.com'],
            'https www stripped'      => ['https://www.example.com',       'example.com'],
            'port stripped'           => ['example.com:8080',              'example.com'],
            'path stripped'           => ['example.com/path/to/page',      'example.com'],
            'https with path'         => ['https://example.com/path?q=1',  'example.com'],
            'https www with path'     => ['https://www.example.com/about', 'example.com'],
            'hash fragment stripped'  => ['example.com#anchor',            'example.com'],
            'localhost returns null'  => ['localhost',                      null],
            'localhost with http'     => ['http://localhost',               null],
            'localhost with port'     => ['localhost:3000',                 null],
            'empty string'            => ['',                               null],
            'null value'              => [null,                             null],
            'subdomain preserved'     => ['sub.example.com',               'sub.example.com'],
            'port with path'          => ['example.com:443/page',          'example.com'],
        ];
    }
}
