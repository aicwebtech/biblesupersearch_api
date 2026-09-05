<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;

/**
 * app.client_url is editable from the admin config form and rendered into an
 * href on the public documentation page, so script-capable schemes must not
 * survive to the markup.
 */
class SafeHrefTest extends TestCase
{
    #[DataProvider('safeHrefDataProvider')]
    public function testSafeHref(mixed $url, ?string $expected): void
    {
        $this->assertSame($expected, Helpers::safeHref($url));
    }

    public static function safeHrefDataProvider(): array
    {
        return [
            'http allowed'          => ['http://example.com', 'http://example.com'],
            'https allowed'         => ['https://example.com/path?a=b', 'https://example.com/path?a=b'],
            'uppercase scheme'      => ['HTTPS://example.com', 'HTTPS://example.com'],
            'javascript rejected'   => ['javascript:alert(1)', null],
            'javascript mixed case' => ['JaVaScRiPt:alert(1)', null],
            'data rejected'         => ['data:text/html;base64,PHNjcmlwdD4=', null],
            'vbscript rejected'     => ['vbscript:msgbox(1)', null],
            'leading space trimmed before scheme check' => ['  javascript:alert(1)', null],
            'schemeless rejected'   => ['example.com', null],
            'protocol relative rejected' => ['//example.com', null],
            'empty string'          => ['', null],
            'null'                  => [null, null],
            'non string'            => [123, null],
        ];
    }
}
