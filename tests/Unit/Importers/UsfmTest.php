<?php

namespace Tests\Unit\Importers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Importers\Usfm;
use ZipArchive;

class UsfmTest extends TestCase
{
    /**
     * Concrete Usfm subclass that records verses in memory (no DB) and exposes
     * the protected formatting hooks for direct assertion.
     */
    private function makeImporter(): Usfm
    {
        return new class extends Usfm {
            /** @var array<int, array{book:int, chapter:int, verse:int, text:string}> */
            public array $recorded = [];

            protected function _addVerse($book, $chapter, $verse, $text, $format_text = FALSE)
            {
                // Mirror the base guard: skip empty flushes at EOF / marker boundaries.
                if(!(int) $book || !(int) $chapter || !(int) $verse || empty($text)) {
                    return;
                }

                $this->recorded[] = [
                    'book'    => (int) $book,
                    'chapter' => (int) $chapter,
                    'verse'   => (int) $verse,
                    'text'    => $this->_formatText($text),
                ];
            }

            public function callZipImportHelper(ZipArchive &$Zip, string $filename)
            {
                return $this->_zipImportHelper($Zip, $filename);
            }

            public function callFormatText(string $text): string
            {
                return $this->_formatText($text);
            }
        };
    }

    /**
     * Builds a single-entry zip in the system temp dir and returns [ZipArchive, entryName, path].
     *
     * @return array{0: ZipArchive, 1: string, 2: string}
     */
    private function makeZip(string $entryName, string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_') . '.zip';
        $Zip  = new ZipArchive();
        $Zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $Zip->addFromString($entryName, $content);
        $Zip->close();

        $Zip = new ZipArchive();
        $Zip->open($path);

        return [$Zip, $entryName, $path];
    }

    private function cleanup(ZipArchive $Zip, string $path): void
    {
        $Zip->close();

        if(file_exists($path)) {
            unlink($path);
        }
    }

    // -----------------------------------------------------------------------
    // Item 1: UTF-8 BOM must not break book detection
    // -----------------------------------------------------------------------

    public function testBomDoesNotBreakBookDetection(): void
    {
        $content = "\xEF\xBB\xBF\\id GEN - Genesis\n\\c 1\n\\v 1 In the beginning.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(1, $imp->recorded);
        $this->assertSame(1, $imp->recorded[0]['book']);
        $this->assertSame(1, $imp->recorded[0]['chapter']);
        $this->assertSame(1, $imp->recorded[0]['verse']);
        $this->assertSame('In the beginning.', $imp->recorded[0]['text']);
    }

    // -----------------------------------------------------------------------
    // Item 2: multi-line verses must not merge words at line breaks
    // -----------------------------------------------------------------------

    public function testMultiLineVerseKeepsWordSpacing(): void
    {
        $content = "\\id GEN - Genesis\n\\c 1\n\\v 1 In the\nbeginning.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(1, $imp->recorded);
        $this->assertSame('In the beginning.', $imp->recorded[0]['text']);
    }

    // -----------------------------------------------------------------------
    // Item 3: files whose names don't start with a digit must still import
    // -----------------------------------------------------------------------

    public function testNonDigitFilenameStillImports(): void
    {
        $content = "\\id MAT - Matthew\n\\c 1\n\\v 1 The book of the generation.\n";
        [$Zip, $entry, $path] = $this->makeZip('MAT.SFM', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(1, $imp->recorded);
        $this->assertSame(40, $imp->recorded[0]['book']);
        $this->assertSame('The book of the generation.', $imp->recorded[0]['text']);
    }

    public function testNonBookFileIsSkipped(): void
    {
        $content = "\\id FRT - Front Matter\n\\p Some introduction text.\n";
        [$Zip, $entry, $path] = $this->makeZip('00FRT.sfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(0, $imp->recorded);
    }

    // -----------------------------------------------------------------------
    // Item 5: a bare "\v N" marker with the text on following lines must import
    // -----------------------------------------------------------------------

    public function testVerseNumberAloneWithTextOnNextLine(): void
    {
        $content = "\\id GEN - Genesis\n\\c 1\n\\v 1\nIn the beginning God\ncreated the heaven.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(1, $imp->recorded);
        $this->assertSame(1, $imp->recorded[0]['verse']);
        $this->assertSame('In the beginning God created the heaven.', $imp->recorded[0]['text']);
    }

    // -----------------------------------------------------------------------
    // Item 6: acrostic (\qa) heading text must not bleed into the previous verse
    // -----------------------------------------------------------------------

    public function testAcrosticHeadingDoesNotBleedIntoPreviousVerse(): void
    {
        $content = "\\id PSA - Psalms\n\\c 119\n\\v 1 Blessed are the undefiled.\n\\qa ALEPH\n\\v 2 Blessed are they that keep.\n";
        [$Zip, $entry, $path] = $this->makeZip('19PSA.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(2, $imp->recorded);
        $this->assertSame('Blessed are the undefiled.', $imp->recorded[0]['text']);
        $this->assertSame('Blessed are they that keep.', $imp->recorded[1]['text']);
    }

    // -----------------------------------------------------------------------
    // Item 7: a leading blank line before \id must not skip the whole book
    // -----------------------------------------------------------------------

    public function testLeadingBlankLineStillImports(): void
    {
        $content = "\n\\id GEN - Genesis\n\\c 1\n\\v 1 In the beginning.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(1, $imp->recorded);
        $this->assertSame(1, $imp->recorded[0]['book']);
        $this->assertSame('In the beginning.', $imp->recorded[0]['text']);
    }

    // -----------------------------------------------------------------------
    // Item 4: word-alignment / milestone markers must be stripped cleanly
    // -----------------------------------------------------------------------

    #[DataProvider('alignmentDataProvider')]
    public function testAlignmentMarkersRemoved(string $input, string $expected): void
    {
        $imp = $this->makeImporter();
        $result = $imp->callFormatText($input);

        $this->assertSame($expected, $result);
        $this->assertStringNotContainsString('\\zaln', $result);
        $this->assertStringNotContainsString('\\*', $result);
    }

    public static function alignmentDataProvider(): array
    {
        return [
            'single aligned word with strongs' => [
                '\zaln-s |x-strong="G1722"\*\w Ἐν|x-strong="G1722"\w*\zaln-e\*',
                'Ἐν{G1722}',
            ],
            'no-space milestone start' => [
                '\zaln-s|x-strong="G0846"\*\w him|x-strong="G0846"\w*\zaln-e\*',
                'him{G0846}',
            ],
            'quotation milestones stripped' => [
                '\qt-s\*Holy\qt-e\*',
                'Holy',
            ],
            'standalone milestone stripped' => [
                'text \ts\* more',
                'text more',
            ],
        ];
    }
}
