<?php

namespace Tests\Unit\Traits\ContentFormatters;

use App\Traits\ContentFormatters\StrongsFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StrongsFormatterTest extends TestCase
{
    private function makeFormatter(): object
    {
        return new class {
            use StrongsFormatter {
                formatRow as public;
                formatNumber as public;
                formatPlainText as public;
                formatDefinition as public;
            }
        };
    }

    #[DataProvider('numberProvider')]
    public function testFormatNumberNormalizesToTheStoredForm(mixed $number, mixed $prefix, mixed $integer, ?string $expected): void
    {
        $this->assertSame($expected, $this->makeFormatter()->formatNumber($number, $prefix, $integer));
    }

    public static function numberProvider(): array
    {
        return [
            'already normal'        => ['H1', NULL, NULL, 'H1'],
            'leading zeros'         => ['H0001', NULL, NULL, 'H1'],
            'lower case prefix'     => ['g25', NULL, NULL, 'G25'],
            'space after prefix'    => ['G 25', NULL, NULL, 'G25'],
            'padded'                => ['  H8674  ', NULL, NULL, 'H8674'],
            'variant letter'        => ['H1234A', NULL, NULL, 'H1234a'],
            'from prefix + integer' => [NULL, 'h', '0007', 'H7'],
            'full number wins'      => ['G1', 'H', '2', 'G1'],
            'integer read as int'   => ['', 'G', 12, 'G12'],
            'nothing'               => [NULL, NULL, NULL, NULL],
            'invalid kept as given' => [' X9 ', NULL, NULL, 'X9'],
            'zero kept as given'    => ['H0', NULL, NULL, 'H0'],
        ];
    }

    public function testFormatPlainTextDecodesEntitiesAndDropsMarkup(): void
    {
        $Formatter = $this->makeFormatter();

        $this->assertSame('\'âb', $Formatter->formatPlainText(' &#39;&acirc;b '));
        $this->assertSame('awb', $Formatter->formatPlainText('<b>awb</b>'));
        $this->assertNull($Formatter->formatPlainText('   '));
        $this->assertNull($Formatter->formatPlainText(NULL));
    }

    public function testFormatDefinitionSanitizesAndKeepsLineBreaks(): void
    {
        $Formatter = $this->makeFormatter();

        $this->assertSame('A <i>green</i> plant', $Formatter->formatDefinition(' A <i>green</i> plant '));
        $this->assertSame('One<br />Two', $Formatter->formatDefinition("One\r\nTwo"));
        $this->assertSame('Safe', $Formatter->formatDefinition('Safe<script>alert(1)</script>'));
        $this->assertNull($Formatter->formatDefinition(''));
        $this->assertNull($Formatter->formatDefinition('<script>alert(1)</script>'));
    }

    public function testFormatRowBuildsTheNumberAndDropsTheExtraFields(): void
    {
        $row = $this->makeFormatter()->formatRow([
            'number_hg'  => 'G',
            'number_int' => '025',
            'root_word'  => ' ἀγαπάω ',
            'definition' => 'To love',
        ]);

        $this->assertEquals([
            'number'     => 'G25',
            'root_word'  => 'ἀγαπάω',
            'definition' => 'To love',
        ], $row);
    }
}
