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
            'magic constant'    => ['__CLASS__', true],
            'magic constant lc' => ['__line__', true],
            'magic constant 8.4'=> ['__PROPERTY__', true],
            'ordinary code en'  => ['en', false],
            'ordinary code de'  => ['de', false],
            'ordinary module'   => ['kjv', false],
            // 'enum' is a *contextual* keyword: `class enum {}` compiles on 8.2-8.5,
            // so rejecting it would turn away a legal module name.
            'enum is allowed'   => ['enum', false],
            // Collides with the built-in Attribute class, but that is legal inside a
            // namespace, which is where every generated class lives.
            'attribute allowed' => ['attribute', false],
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
     * Words that only became reserved after the oldest supported release, mapped to
     * the version that reserves them. They stay in the list because 8.4 and 8.5 are
     * supported, but the running PHP cannot be asked to reject them below that.
     *
     * @var array<string, string>
     */
    private const RESERVED_FROM = [
        '__property__' => '8.4',
    ];

    /**
     * Pins the list to what PHP actually rejects, so an invented or misspelled entry
     * cannot sit there silently turning away legal module names.
     *
     * Each word is tried in a subprocess because the soft-reserved failure ("Cannot
     * use 'int' as class name as it is reserved") is a fatal, not a catchable Error,
     * so eval() in this process would abort the test run.
     *
     * Namespaced deliberately: a reserved word is illegal even inside a namespace,
     * whereas a mere collision with a built-in class (Attribute, Closure) is not.
     * That is the distinction the list is meant to draw.
     */
    public function testEveryListedWordIsRejectedByPhp(): void
    {
        $checked = 0;

        foreach(Helpers::phpReservedWords() as $word) {
            $minimum = self::RESERVED_FROM[strtolower($word)] ?? null;

            if($minimum !== null && version_compare(PHP_VERSION, $minimum, '<')) {
                continue;
            }

            $code = 'namespace App\\Models\\Books; class ' . $word . ' {}';
            $command = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -d display_errors=0 -r '
                . escapeshellarg($code) . ' 2>/dev/null';

            exec($command, $output, $status);

            $this->assertNotSame(
                0,
                $status,
                'PHP ' . PHP_VERSION . ' accepts "' . $word . '" as a class name, so listing it as '
                . 'reserved turns away a legal name'
            );

            $checked++;
        }

        $this->assertGreaterThan(80, $checked, 'Expected the full reserved-word list to be exercised');
    }

    /**
     * The other half of the contract: a word PHP accepts must not be listed. Guards
     * the specific cases that have been argued about.
     */
    #[DataProvider('legalClassNameProvider')]
    public function testWordsPhpAcceptsAreNotListed(string $word): void
    {
        $code = 'namespace App\\Models\\Books; class ' . $word . ' {}';
        $command = escapeshellarg(PHP_BINARY) . ' -d error_reporting=0 -d display_errors=0 -r '
            . escapeshellarg($code) . ' 2>/dev/null';

        exec($command, $output, $status);

        $this->assertSame(0, $status, 'Precondition: PHP ' . PHP_VERSION . ' accepts ' . $word);
        $this->assertFalse(Helpers::isReservedPhpWord($word), $word . ' is legal and must not be listed');
    }

    public static function legalClassNameProvider(): array
    {
        return [
            'enum (contextual keyword)' => ['enum'],
            'attribute (built-in class)' => ['attribute'],
            'from (contextual)' => ['from'],
            'ordinary module' => ['kjv'],
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
