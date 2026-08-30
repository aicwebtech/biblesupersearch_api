<?php

namespace Tests\Unit\Models;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Models\Language;

class LanguageTest extends TestCase
{
    public function testInstance() 
    {
        $language = new Language();
        $this->assertInstanceOf(Language::class, $language);
    }

    /**
     * Builds an unsaved Language. Everything asserted below reads or writes attributes on
     * the instance only - the database-backed helpers live in Tests\Feature\Models.
     *
     * @param array<string, mixed> $attributes
     */
    private function makeLanguage(array $attributes = []): Language
    {
        $language = new Language();

        foreach ($attributes as $key => $value) {
            $language->{$key} = $value;
        }

        return $language;
    }

    public function testTimestampsAreDisabled(): void
    {
        $this->assertFalse((new Language())->timestamps);
    }

    public function testTtsFieldsAreMassAssignable(): void
    {
        $fillable = (new Language())->getFillable();

        $this->assertContains('tts_api', $fillable);
        $this->assertContains('tts_voice', $fillable);
        $this->assertContains('tts_speed', $fillable);
    }

    /**
     * The rtl column is written from free-form import data, so the mutator has to read
     * "false" and "no" as negatives rather than as truthy non-empty strings.
     *
     * Null is covered because an empty rtl column imports as null: setRtlAttribute() casts to
     * string before calling strtolower(), which would otherwise be a deprecation on PHP 8.1+
     * and an error on PHP 9.
     *
     * @return array<string, array{mixed, int}>
     */
    public static function rtlProvider(): array
    {
        return [
            'null'                 => [null, 0],
            'literal false string' => ['false', 0],
            'uppercase FALSE'      => ['FALSE', 0],
            'no'                   => ['no', 0],
            'uppercase NO'         => ['NO', 0],
            'empty string'         => ['', 0],
            'zero string'          => ['0', 0],
            'one'                  => ['1', 1],
            'yes'                  => ['yes', 1],
            'true string'          => ['true', 1],
        ];
    }

    #[DataProvider('rtlProvider')]
    public function testRtlMutatorNormalisesImportValues(mixed $input, int $expected): void
    {
        $language = $this->makeLanguage(['rtl' => $input]);

        $this->assertSame($expected, $language->getAttributes()['rtl']);
    }

    public function testRtlAccessorReturnsABoolean(): void
    {
        $this->assertTrue($this->makeLanguage(['rtl' => '1'])->rtl());
        $this->assertFalse($this->makeLanguage(['rtl' => 'no'])->rtl());
    }

    /**
     * iso_639_3 is derived from the raw column, which may carry trailing detail beyond the
     * three-letter code.
     */
    public function testIso6393IsDerivedFromTheRawValue(): void
    {
        $language = $this->makeLanguage(['iso_639_3_raw' => '  engadditional  ']);

        $this->assertSame('engadditional', $language->getAttributes()['iso_639_3_raw']);
        $this->assertSame('eng', $language->getAttributes()['iso_639_3']);
    }

    public function testNativeNameIsTitleCased(): void
    {
        $this->assertSame('Deutsche Sprache', $this->makeLanguage(['native_name' => 'deutsche sprache'])->native_name);
    }

    public function testNativeNameIsNullWhenNotAString(): void
    {
        $this->assertNull($this->makeLanguage(['native_name' => null])->native_name);
    }

    public function testCommonWordsAreSplitOnNewlinesAndLowercased(): void
    {
        $language = $this->makeLanguage(['common_words' => "The\r\nAND\nOf"]);

        $this->assertSame(['the', 'and', 'of'], $language->getCommonWordsAsArray());
    }

    public function testCommonWordsSkipsBlankLines(): void
    {
        $language = $this->makeLanguage(['common_words' => "the\n\n\nand\n"]);

        $this->assertSame(['the', 'and'], $language->getCommonWordsAsArray());
    }

    public function testCommonWordsIsEmptyWhenUnset(): void
    {
        $this->assertSame([], (new Language())->getCommonWordsAsArray());
    }

    public function testFormatEnglishNameCombinesNativeAndEnglishNames(): void
    {
        $language = $this->makeLanguage(['name' => 'German', 'native_name' => 'Deutsch']);

        $this->assertSame('Deutsch (German)', $language->formatEnglishName());
    }

    /**
     * English needs no parenthetical - the two names are identical.
     */
    public function testFormatEnglishNameCollapsesWhenTheNamesMatch(): void
    {
        $language = $this->makeLanguage(['name' => 'English', 'native_name' => 'English']);

        $this->assertSame('English', $language->formatEnglishName());
    }

    public function testFormatNameCodeUppercasesTheCode(): void
    {
        $language = $this->makeLanguage(['name' => 'German', 'code' => 'de']);

        $this->assertSame('German (DE)', $language->formatNameCode());
    }

    /**
     * Chinese is stored under one language row but serves three book-list codes, so
     * getAllCodes() fans out for zh and only zh.
     */
    public function testChineseFansOutToItsRegionalCodes(): void
    {
        $this->assertSame(['zh', 'zh_cn', 'zh_tw'], $this->makeLanguage(['code' => 'zh'])->getAllCodes());
    }

    public function testOtherLanguagesReturnOnlyTheirOwnCode(): void
    {
        $this->assertSame(['de'], $this->makeLanguage(['code' => 'de'])->getAllCodes());
    }

    public function testUpdateRulesRequireTheCoreFields(): void
    {
        $rules = Language::getUpdateRules();

        $this->assertContains('required', $rules['code']);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('required', $rules['native_name']);
    }

    public function testUpdateRulesConstrainTheCodeToTwoOrThreeLetters(): void
    {
        $rules = Language::getUpdateRules();

        $this->assertContains('alpha', $rules['code']);
        $this->assertContains('min:2', $rules['code']);
        $this->assertContains('max:3', $rules['code']);
    }

    /**
     * The unique rules must exempt the row being edited, or saving a language without
     * renaming it would fail its own uniqueness check.
     */
    public function testUpdateRulesIgnoreTheRowBeingEdited(): void
    {
        $rules = Language::getUpdateRules(17);

        $this->assertStringContainsString('17', (string) end($rules['code']));
    }

    public function testTtsSpeedIsBounded(): void
    {
        $rules = Language::getUpdateRules();

        $this->assertSame('nullable|numeric|min:0.25|max:4.0', $rules['tts_speed']);
    }
}
