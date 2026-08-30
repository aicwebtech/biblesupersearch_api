<?php

namespace Tests\Unit\Importers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Importers\BibleSuperSearch;
use App\Importers\Evening;
use App\Importers\ImporterAbstract;
use App\Importers\Irv;
use App\Importers\MyBible;
use App\Importers\MySword;
use App\Importers\Usfm;
use App\Importers\Rvg;
use App\Importers\Text;

/**
 * Every importer declares the markup delimiters its source format uses; ImporterAbstract
 * translates those into the canonical Bible SuperSearch markup that the renderers and the
 * TTS formatter expect:
 *
 *   italics    -> [ ]
 *   red letter -> ‹ › (U+2039 / U+203A, deliberately not < >)
 *   Strong's   -> { }
 *
 * A wrong delimiter here corrupts every verse an importer touches, and the importers'
 * _importHelper() methods cannot be reached in a test because they read fixed files from the
 * untracked bibles/ directories. This pins the part that is reachable.
 *
 * Reflection is used because the formatters are protected; the importers are constructed
 * directly, which touches neither the database nor the application.
 *
 * MyBible and MySword are both covered and both matter: the two formats are easily confused
 * (MyBible .SQLite3 uses HTML-ish <i>/<J>/<S>, MySword .mybible uses GBF <FI>/<FR>), and
 * app/Importers/MyBible.php previously declared "class MySword", making App\Importers\MyBible
 * unloadable and leaving the MyBible delimiters entirely untested.
 */
class ImporterMarkupTest extends TestCase
{
    private function format(ImporterAbstract $importer, string $method, string $text): string
    {
        return (new \ReflectionMethod(ImporterAbstract::class, $method))->invoke($importer, $text);
    }

    // -----------------------------------------------------------------------
    // Italics
    // -----------------------------------------------------------------------

    public function testMySwordItalicTagsBecomeBrackets(): void
    {
        $formatted = $this->format(new MySword(), '_formatItalics', 'the <FI>LORD<Fi> said');

        $this->assertSame('the [LORD] said', $formatted);
    }

    public function testMyBibleItalicTagsBecomeBrackets(): void
    {
        $formatted = $this->format(new MyBible(), '_formatItalics', 'the <i>LORD</i> said');

        $this->assertSame('the [LORD] said', $formatted);
    }

    public function testUsfmItalicMarkersBecomeBrackets(): void
    {
        $formatted = $this->format(new Usfm(), '_formatItalics', 'the \\add LORD\\add* said');

        $this->assertSame('the [LORD] said', $formatted);
    }

    /**
     * Formats that already use the canonical delimiters take the no-op branch of
     * _replaceTagsIfNeeded rather than a pointless str_replace.
     *
     * @return array<string, array{ImporterAbstract}>
     */
    public static function alreadyBracketedProvider(): array
    {
        return [
            'Irv'     => [new Irv()],
            'Evening' => [new Evening()],
            'Rvg'     => [new Rvg()],
            'BSS'     => [new BibleSuperSearch()],
        ];
    }

    #[DataProvider('alreadyBracketedProvider')]
    public function testImportersAlreadyUsingBracketsLeaveItalicsAlone(ImporterAbstract $importer): void
    {
        $this->assertSame('the [LORD] said', $this->format($importer, '_formatItalics', 'the [LORD] said'));
    }

    // -----------------------------------------------------------------------
    // Red letter
    // -----------------------------------------------------------------------

    public function testMySwordRedLetterTagsBecomeGuillemets(): void
    {
        $formatted = $this->format(new MySword(), '_formatRedLetter', '<FR>I am he<Fr>');

        $this->assertSame('‹I am he›', $formatted);
    }

    public function testMyBibleRedLetterTagsBecomeGuillemets(): void
    {
        $formatted = $this->format(new MyBible(), '_formatRedLetter', '<J>I am he</J>');

        $this->assertSame('‹I am he›', $formatted);
    }

    public function testUsfmRedLetterMarkersBecomeGuillemets(): void
    {
        $formatted = $this->format(new Usfm(), '_formatRedLetter', '\\wj I am he\\wj*');

        $this->assertSame('‹I am he›', $formatted);
    }

    /**
     * The angle brackets these formats use are ordinary ASCII < >, which would be eaten by
     * strip_tags downstream - hence the conversion to U+2039 / U+203A.
     */
    public function testAngleBracketFormatsAreConvertedToGuillemets(): void
    {
        $this->assertSame('‹I am he›', $this->format(new Irv(), '_formatRedLetter', '<I am he>'));
        $this->assertSame('‹I am he›', $this->format(new Rvg(), '_formatRedLetter', '<I am he>'));
    }

    /**
     * Evening and BibleSuperSearch declare no red-letter delimiters, so the text must pass
     * through untouched rather than having empty markers substituted into it.
     */
    public function testFormatsWithoutRedLetterMarkupPassTextThrough(): void
    {
        $this->assertSame('I am he', $this->format(new Evening(), '_formatRedLetter', 'I am he'));
        $this->assertSame('I am he', $this->format(new BibleSuperSearch(), '_formatRedLetter', 'I am he'));
    }

    // -----------------------------------------------------------------------
    // Strong's numbers
    // -----------------------------------------------------------------------

    /**
     * MyBible is the only importer in this suite declaring Strong's delimiters, so it is the
     * only one whose tags are actually rewritten to braces.
     */
    public function testMyBibleStrongsTagsBecomeBraces(): void
    {
        $formatted = $this->format(new MyBible(), '_formatStrongs', 'beginning<S>H7225</S>');

        $this->assertStringContainsString('{H7225}', $formatted);
        $this->assertStringNotContainsString('<S>', $formatted);
    }

    public function testFormatsWithoutStrongsMarkupPassTextThrough(): void
    {
        $text = 'In the beginning God created';

        $this->assertSame($text, $this->format(new Irv(), '_formatStrongs', $text));
        $this->assertSame($text, $this->format(new Rvg(), '_formatStrongs', $text));
        $this->assertSame($text, $this->format(new Text(), '_formatStrongs', $text));
        $this->assertSame($text, $this->format(new Usfm(), '_formatStrongs', $text));
    }

    // -----------------------------------------------------------------------
    // Shared post-formatting
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{string, string}>
     */
    public static function postFormatProvider(): array
    {
        return [
            'strips html'                 => ['<p>In the beginning</p>', 'In the beginning'],
            'collapses runs of whitespace' => ["In   the\n\nbeginning", 'In the beginning'],
            'closes space before comma'   => ['In the beginning , God', 'In the beginning, God'],
            'closes space before period'  => ['the earth .', 'the earth.'],
            'closes space before colon'   => ['saying :', 'saying:'],
            'leaves clean text alone'     => ['In the beginning God created', 'In the beginning God created'],
        ];
    }

    #[DataProvider('postFormatProvider')]
    public function testPostFormatNormalisesText(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->format(new Irv(), '_postFormatText', $input));
    }

    // -----------------------------------------------------------------------
    // Declared requirements
    // -----------------------------------------------------------------------

    /**
     * @return array<string, array{ImporterAbstract}>
     */
    public static function requiredFieldProvider(): array
    {
        return [
            'Irv'     => [new Irv()],
            'Evening' => [new Evening()],
            'Rvg'     => [new Rvg()],
            'Text'    => [new Text()],
        ];
    }

    /**
     * Without module and language an imported Bible cannot be addressed or searched, so
     * every importer declaring $required must ask for them.
     */
    #[DataProvider('requiredFieldProvider')]
    public function testImportersRequireModuleAndLanguage(ImporterAbstract $importer): void
    {
        $property = new \ReflectionProperty(ImporterAbstract::class, 'required');
        $required = $property->getValue($importer);

        $this->assertContains('module', $required);
        $this->assertContains('lang', $required);
        $this->assertContains('lang_short', $required);
    }
}
