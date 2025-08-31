<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Models\Language;

class LanguageTest extends TestCase
{
    #[DataProvider('rtlCheckDataProvider')]
    public function testRtlCheck(string|null $lang, bool $expected): void 
    {
        $this->assertSame($expected, Language::isRtl($lang));
    }

    public static function rtlCheckDataProvider(): array 
    {
        return [
            [ 'he', true ],
            [ 'ar', true ],
            [ 'en', false ],
            [ 'es', false ],
            [ 'zz', false ],
            [ 'zzz', false ],
            [ 'abcd', false ],
            [ '', false ],
            [ null, false ],
            [ 'mer-234324', false],
            ['123', false],
            ['<script>hackit()</script>', false],
            ['; DROP TABLE users; --', false],
            ['; dangerous(); ', false],
            [ 'dne', false ], // does not exist
        ];
    }

    #[DataProvider('providerValidateLanguage')]
    public function testValidateLanguage(string|null $lang, bool $expected): void
    {
        $this->assertSame($expected, Language::validateLanguage($lang));
    }

    public static function providerValidateLanguage(): array 
    {
        return [
            [ 'en', true ],
            [ 'es', true ],
            [ 'fr', true ],
            [ 'de', true ],
            [ 'zz', false ],
            [ 'zzz', false ],
            [ 'abcd', false ],
            [ '', false ],
            [ null, false ],
            [ 'mer-234324', false],
            ['123', false],
            ['<script>hackit()</script>', false],
            ['; DROP TABLE users; --', false],
            ['; dangerous(); ', false],
            [ 'dne', false ], // does not exist
        ];
    }
}
