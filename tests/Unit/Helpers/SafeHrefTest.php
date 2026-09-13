<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;

/**
 * app.client_url is editable from the admin config form and rendered into an
 * href on the public documentation page, so script-capable schemes must not
 * survive to the markup.
 *
 * Only a scheme-like prefix is judged. A value with no scheme at all is a
 * relative or protocol-relative URL, cannot invoke script, and is a legitimate
 * way to configure app.client_url, so it passes through.
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
            'schemeless host allowed'    => ['www.example.com/client', 'www.example.com/client'],
            'protocol relative allowed'  => ['//cdn.example.com/client', '//cdn.example.com/client'],
            'root relative allowed'      => ['/client', '/client'],
            'query only allowed'         => ['?a=b', '?a=b'],
            'unschemed host port rejected as ambiguous' => ['example.com:8080/client', null],
            'tab inside scheme rejected' => ["jav\tascript:alert(1)", null],
            'newline inside scheme rejected' => ["java\nscript:alert(1)", null],
            'null byte inside scheme rejected' => ["java\0script:alert(1)", null],
            'other scheme rejected'      => ['ftp://example.com', null],
            'mailto rejected'            => ['mailto:someone@example.com', null],
            'empty string'          => ['', null],
            'null'                  => [null, null],
            'non string'            => [123, null],
        ];
    }
}
