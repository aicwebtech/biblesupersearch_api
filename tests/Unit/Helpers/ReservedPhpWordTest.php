<?php

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Helpers;
use App\Models\Books\BookAbstract;

/**
 * Language codes and Bible module names are studly-cased into generated PHP
 * class names. A name that collides with a reserved word is a fatal parse error
 * ("class For extends ..."), which would break every request for the affected
 * language or Bible.
 */
class ReservedPhpWordTest extends TestCase
{
    #[DataProvider('reservedWordProvider')]
    public function testIsReservedPhpWord(mixed $word, bool $expected): void
    {
        $this->assertSame($expected, Helpers::isReservedPhpWord($word));
    }

    public static function reservedWordProvider(): array
    {
        return [
            'keyword as'        => ['as', true],
            'keyword or'        => ['or', true],
            'keyword for'       => ['for', true],
            'keyword new'       => ['new', true],
            'case insensitive'  => ['As', true],
            'mixed case'        => ['ReadOnly', true],
            'soft reserved int' => ['int', true],
            'soft reserved never' => ['never', true],
            'ordinary code en'  => ['en', false],
            'ordinary code de'  => ['de', false],
            'ordinary module'   => ['kjv', false],
            'enum is allowed'   => ['enum', false],
            'empty'             => ['', false],
            'null'              => [null, false],
            'non string'        => [123, false],
        ];
    }

    /**
     * Only colliding names get a prefix, so existing generated classes keep
     * their names and no migration is required.
     */
    #[DataProvider('safeClassNameProvider')]
    public function testSafeGeneratedClassName(string $base, string $expected): void
    {
        $this->assertSame($expected, Helpers::safeGeneratedClassName($base));
    }

    public static function safeClassNameProvider(): array
    {
        return [
            'unchanged when safe' => ['En', 'En'],
            'unchanged when safe 2' => ['Kjv', 'Kjv'],
            'prefixed when reserved' => ['As', 'LangAs'],
            'prefixed when reserved 2' => ['Or', 'LangOr'],
        ];
    }

    /**
     * 'as' (Assamese) and 'or' (Odia) are real ISO 639-1 codes, so they must
     * keep working rather than being rejected.
     */
    #[DataProvider('languageClassProvider')]
    public function testLanguageClassBaseNameIsAlwaysLegal(string $code, string $expected): void
    {
        $base = BookAbstract::getClassBaseName($code);

        $this->assertSame($expected, $base);
        $this->assertFalse(
            Helpers::isReservedPhpWord($base),
            'Generated class name would be a fatal parse error: ' . $base
        );
    }

    public static function languageClassProvider(): array
    {
        return [
            'english'  => ['en', 'En'],
            'german'   => ['de', 'De'],
            'assamese' => ['as', 'LangAs'],
            'odia'     => ['or', 'LangOr'],
        ];
    }
}
