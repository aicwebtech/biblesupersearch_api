<?php

namespace Tests\Unit\Models;

use App\Models\Books\BookAbstract;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class BookAbstractTest extends TestCase
{
    public function testGetClassNameByLanguageRaw()
    {
        $class = BookAbstract::getClassNameByLanguageRaw('en');
        $this->assertEquals('App\Models\Books\En', $class);
    }

    // :todo mock this
    // public function testGetClassNameByLanguageReturnsDefaultIfNotExists()
    // {
    //     // Simulate config('app.locale') returns 'en'
    //     $default = 'App\Models\Books\En';
    //     $this->assertEquals($default, BookAbstract::getClassNameByLanguage('notexist', false));
    // }

    public function testGetClassNameByLanguageStrictReturnsFalseIfNotExists()
    {
        $this->assertFalse(BookAbstract::getClassNameByLanguageStrict('notexist', false));
    }

    public function testGetLanguageReturnsClassNameLowercase()
    {
        $this->assertEquals('app\models\books\bookabstract', BookAbstract::getLanguage());
    }

    public function testGetCsvFileName()
    {
        $this->assertEquals('bible_books/en.csv', BookAbstract::getCsvFileName('en'));
    }

    // :todo mock this
    // public function testIsSupportedLanguage()
    // {
    //     // This will call \App\Models\Language::hasBookSupport, which should be mocked in a real test
    //     $this->assertIsBool(BookAbstract::isSupportedLanguage('en'));
    // }

    public function testGetSupportedLanguagesContainsEn()
    {
        $langs = BookAbstract::getSupportedLanguages();
        $this->assertContains('en', $langs);
    }

    /**
     * Book ids address the 66 canonical books. Anything outside that range must be rejected
     * before it reaches a query, since the id is interpolated into table and class names.
     *
     * @return array<string, array{mixed, bool}>
     */
    public static function bookIdProvider(): array
    {
        return [
            'first book'           => [1, true],
            'last book'            => [66, true],
            'middle book'          => [43, true],
            'numeric string'       => ['43', true],
            'padded numeric string' => [' 43 ', true],
            'zero'                 => [0, false],
            'negative'             => [-1, false],
            'past the last book'   => [67, false],
            'far out of range'     => [999, false],
            'not a number'         => ['Genesis', false],
            'empty string'         => ['', false],
            'decimal'              => ['43.5', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bookIdProvider')]
    public function testIsValidBookId(mixed $id, bool $expected): void
    {
        $this->assertSame($expected, BookAbstract::isValidBookId($id));
    }

    private function normalize(?string $value): string
    {
        $method = new \ReflectionMethod(BookAbstract::class, 'normalizeForMatch');

        return $method->invoke(null, $value);
    }

    /**
     * Book names are matched against user input, so the normaliser has to absorb the ways a
     * reader might type a name: casing, padding, and repeated spaces.
     */
    public function testNormalisationLowercasesAndTrims(): void
    {
        $this->assertSame('genesis', $this->normalize('  Genesis  '));
    }

    public function testNormalisationCollapsesRepeatedWhitespace(): void
    {
        $this->assertSame('song of solomon', $this->normalize("Song   of\tSolomon"));
    }

    /**
     * Accent folding is what lets a reader type an unaccented name and still match, on
     * installations where the intl extension is unavailable.
     */
    public function testNormalisationFoldsAccents(): void
    {
        $this->assertSame('exodo', $this->normalize('Éxodo'));
        $this->assertSame('levitico', $this->normalize('Levítico'));
    }

    public function testNormalisationHandlesEmptyAndNullInput(): void
    {
        $this->assertSame('', $this->normalize(''));
        $this->assertSame('', $this->normalize('   '));
        $this->assertSame('', $this->normalize(null));
    }

    /**
     * Normalisation must be idempotent - an already-normalised name is what gets stored in
     * the match index, and running it through again must not change it.
     */
    public function testNormalisationIsIdempotent(): void
    {
        $once = $this->normalize('  Éxodo   Segundo ');

        $this->assertSame($once, $this->normalize($once));
    }

    /**
     * Every entry in the folding map must reduce to a plain ASCII letter, or matching would
     * simply swap one accented form for another.
     */
    public function testTheAccentFoldingMapProducesAsciiOnly(): void
    {
        $method = new \ReflectionMethod(BookAbstract::class, 'accentFoldingMap');
        $map    = $method->invoke(null);

        $this->assertNotEmpty($map);

        foreach ($map as $accented => $plain) {
            $this->assertMatchesRegularExpression('/^[a-z]*$/', $plain, "folding of '{$accented}'");
        }
    }
}