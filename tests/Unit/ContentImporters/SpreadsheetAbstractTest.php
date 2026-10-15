<?php

namespace Tests\Unit\ContentImporters;

use App\ContentImporters\SpreadsheetAbstract;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpreadsheetAbstractTest extends TestCase
{
    /**
     * A concrete spreadsheet importer over in-memory rows: no file I/O
     */
    private function makeImporter(): SpreadsheetAbstract
    {
        return new class('strongs') extends SpreadsheetAbstract {
            public function callMapRow(array $raw, int $row): array
            {
                return $this->mapRow($raw, $row);
            }

            protected function openFile(string $path): bool { return TRUE; }
            protected function readBatches(int $size): \Generator { yield []; }
            protected function closeFile(): void { }
        };
    }

    public function testSettingsBuildTheColumnMap(): void
    {
        $Importer = $this->makeImporter();

        $this->assertTrue($Importer->setSettings([
            'first_row_data' => '3',
            'col_a' => 'number',
            'col_B' => 'none',
            'col_C' => NULL,
            'col_AA' => 'definition',
            'importer' => 'csv',
        ]), implode(' ', (array) $Importer->getErrors()));

        $this->assertSame([0 => 'number', 26 => 'definition'], $Importer->getColumnMap());
        $this->assertSame(3, $Importer->getFirstRowData());
    }

    public function testAnUnknownFieldIsAnError(): void
    {
        $Importer = $this->makeImporter();

        $this->assertFalse($Importer->setSettings(['first_row_data' => 1, 'col_A' => 'number', 'col_B' => 'definition', 'col_C' => 'bacon']));
        $this->assertStringContainsString('Column C: unknown field \'bacon\'', implode(' ', $Importer->getErrors()));
    }

    public function testRequiredFieldsMustBeMapped(): void
    {
        $Importer = $this->makeImporter();

        $this->assertFalse($Importer->setSettings(['first_row_data' => 1, 'col_A' => 'root_word']));

        $errors = implode(' ', $Importer->getErrors());
        $this->assertStringContainsString('Please specify a column for Number', $errors);
        $this->assertStringContainsString('Please specify a column for Definition', $errors);
    }

    public function testARequiredFieldCanBeMappedThroughItsAlternatives(): void
    {
        $Importer = $this->makeImporter();
        $this->assertTrue($Importer->setSettings(['first_row_data' => 1, 'col_A' => 'number_hg', 'col_B' => 'number_int', 'col_C' => 'definition']));

        $Importer = $this->makeImporter();
        $this->assertFalse($Importer->setSettings(['first_row_data' => 1, 'col_A' => 'number_hg', 'col_C' => 'definition']), 'Half an alternative is not enough');
    }

    #[DataProvider('badFirstRowProvider')]
    public function testFirstRowOfDataMustBeAPositiveInteger(mixed $value): void
    {
        $Importer = $this->makeImporter();

        $this->assertFalse($Importer->setSettings(['first_row_data' => $value, 'col_A' => 'number', 'col_B' => 'definition']));
        $this->assertStringContainsString('First Row of Data', implode(' ', $Importer->getErrors()));
    }

    public static function badFirstRowProvider(): array
    {
        return [
            'missing'  => [NULL],
            'empty'    => [''],
            'zero'     => ['0'],
            'negative' => ['-1'],
            'text'     => ['two'],
        ];
    }

    public function testMapRowTakesTheFirstNonEmptyValueForAField(): void
    {
        $Importer = $this->makeImporter();
        $Importer->setSettings(['first_row_data' => 1, 'col_A' => 'number', 'col_B' => 'definition', 'col_C' => 'definition']);

        $this->assertSame(
            ['number' => 'H1', 'definition' => 'Fallback'],
            $Importer->callMapRow([' H1 ', '', 'Fallback', 'ignored'], 2)
        );

        $this->assertSame(['number' => 'H2'], $Importer->callMapRow(['H2'], 3), 'A short row maps what it has');
    }

    public function testMapRowRefusesAFormula(): void
    {
        $Importer = $this->makeImporter();
        $Importer->setSettings(['first_row_data' => 1, 'col_A' => 'number', 'col_B' => 'definition']);

        $Importer->callMapRow(['H1', '=A1&"x"'], 7);

        $this->assertStringContainsString('Row 7, column B contains a formula', implode(' ', $Importer->getErrors()));
    }

    #[DataProvider('columnProvider')]
    public function testColumnLettersAndNumbersRoundTrip(string $letters, int $number): void
    {
        $this->assertSame($number, SpreadsheetAbstract::columnToNumber($letters));
        $this->assertSame($letters, SpreadsheetAbstract::numberToColumn($number));
    }

    public static function columnProvider(): array
    {
        return [
            ['A', 1], ['Z', 26], ['AA', 27], ['AZ', 52], ['BA', 53], ['ZZ', 702], ['AAA', 703],
        ];
    }

    public function testColumnLettersAreCaseInsensitiveAndZeroHasNoLetter(): void
    {
        $this->assertSame(28, SpreadsheetAbstract::columnToNumber('ab'));
        $this->assertNull(SpreadsheetAbstract::numberToColumn(0));
    }

    public function testAnUnknownContentTypeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new class('bacon') extends SpreadsheetAbstract {
            protected function openFile(string $path): bool { return TRUE; }
            protected function readBatches(int $size): \Generator { yield []; }
            protected function closeFile(): void { }
        };
    }
}
