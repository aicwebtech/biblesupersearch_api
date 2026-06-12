<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Engine;

class EngineTest extends TestCase
{
    #[DataProvider('sanitizeStringDataProvider')]
    public function testSanitizeString(string $input, string $expected): void
    {
        $this->assertSame($expected, Engine::sanitizeString($input));
    }

    public static function sanitizeStringDataProvider(): array
    {
        return [
            'plain text unchanged'           => ['hello world',              'hello world'],
            'leading whitespace trimmed'     => ['  hello',                  'hello'],
            'trailing whitespace trimmed'    => ['hello  ',                  'hello'],
            'html tags stripped'             => ['<b>bold</b>',              'bold'],
            'nested tags stripped'           => ['<p>one <em>two</em></p>',  'one two'],
            'script tag stripped'            => ['<script>alert(1)</script>','alert(1)'],
            'multiple spaces collapsed'      => ['faith  hope   joy',        'faith hope joy'],
            'tabs collapsed'                 => ["faith\t\thope",            'faith hope'],
            'newline chars collapsed'        => ["faith\nhope",              'faith hope'],
            'literal backslash-n replaced'   => ['faith\nhope',             'faith hope'],
            'empty string'                   => ['',                         ''],
            'only whitespace'                => ['   ',                      ''],
        ];
    }
}
