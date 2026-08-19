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

            public function callImportContents(string $bib, string $filename): bool
            {
                return $this->_importContents($bib, $filename);
            }

            public function callFormatText(string $text): string
            {
                return $this->_formatText($text);
            }

            public function getBookMetas(): array
            {
                return $this->book_metas;
            }

            public function getBibleAttributes(): array
            {
                return $this->bible_attributes;
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
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        if($path === false) {
            $this->fail('Failed to create temporary file for USFM zip test.');
        }
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
    // Multiple books in a single file: every verse of every book must import,
    // including the last verse before the next \id, and \ide must be ignored
    // -----------------------------------------------------------------------

    public function testMultiBookFileImportsAllVersesOfAllBooks(): void
    {
        $content = "\\id GEN - Genesis\n\\ide UTF-8\n\\toc2 Genesis\n\\c 1\n"
            . "\\v 1 In the beginning.\n\\v 2 And the earth was without form.\n"
            . "\\id EXO - Exodus\n\\ide UTF-8\n\\toc2 Exodus\n\\c 1\n"
            . "\\v 1 Now these are the names.\n";
        [$Zip, $entry, $path] = $this->makeZip('bible.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(3, $imp->recorded);
        $this->assertSame([1, 1, 2], array_column($imp->recorded, 'book'));
        $this->assertSame('And the earth was without form.', $imp->recorded[1]['text']);
        $this->assertSame('Now these are the names.', $imp->recorded[2]['text']);
    }

    // -----------------------------------------------------------------------
    // A non-canonical book mid-file must be skipped entirely — its verses must
    // not be imported under the previous book, and later books must still import
    // -----------------------------------------------------------------------

    public function testNonCanonicalBookMidFileDoesNotBleedIntoPreviousBook(): void
    {
        $content = "\\id GEN - Genesis\n\\c 1\n\\v 1 In the beginning.\n"
            . "\\id TOB - Tobit\n\\c 1\n\\v 1 Apocryphal text here.\n"
            . "\\id EXO - Exodus\n\\c 1\n\\v 1 Now these are the names.\n";
        [$Zip, $entry, $path] = $this->makeZip('bible.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(2, $imp->recorded);
        $this->assertSame([1, 2], array_column($imp->recorded, 'book'));
        $this->assertNotContains('Apocryphal text here.', array_column($imp->recorded, 'text'));
    }

    // -----------------------------------------------------------------------
    // A file LEADING with a non-canonical book (e.g. front matter) must still
    // import the canonical books that follow it
    // -----------------------------------------------------------------------

    public function testFrontMatterFirstFileStillImportsCanonicalBooks(): void
    {
        $content = "\\id FRT - Front Matter\n\\p Some introduction text.\n"
            . "\\id GEN - Genesis\n\\c 1\n\\v 1 In the beginning.\n";
        [$Zip, $entry, $path] = $this->makeZip('bible.usfm', $content);

        $imp = $this->makeImporter();
        $result = $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertTrue($result);
        $this->assertCount(1, $imp->recorded);
        $this->assertSame(1, $imp->recorded[0]['book']);
        $this->assertSame('In the beginning.', $imp->recorded[0]['text']);
    }

    // -----------------------------------------------------------------------
    // Chapter metadata markers (\cl, \cp, \ca, \cd) must never set the chapter
    // -----------------------------------------------------------------------

    public function testChapterMetadataMarkersDoNotSetChapter(): void
    {
        $content = "\\id GEN - Genesis\n\\c 1\n\\cl Chapter One\n\\cp 99\n\\v 1 First verse.\n"
            . "\\c 2\n\\cd A chapter about 40 days.\n\\v 1 Second chapter verse.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(2, $imp->recorded);
        $this->assertSame([1, 2], array_column($imp->recorded, 'chapter'));
    }

    // -----------------------------------------------------------------------
    // Chapter markers with no whitespace before the number (\c1) must still set
    // the chapter, without being confused for \ca/\cl/\cp/\cd metadata
    // -----------------------------------------------------------------------

    public function testChapterMarkerWithoutSpaceStillSetsChapter(): void
    {
        $content = "\\id GEN - Genesis\n\\c1\n\\v 1 First verse.\n\\c2\n\\v 1 Second chapter verse.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(2, $imp->recorded);
        $this->assertSame([1, 2], array_column($imp->recorded, 'chapter'));
        $this->assertSame('Second chapter verse.', $imp->recorded[1]['text']);
    }

    // -----------------------------------------------------------------------
    // \toc names must be trimmed (tolerate extra whitespace after the marker)
    // -----------------------------------------------------------------------

    public function testTocNamesAreTrimmed(): void
    {
        $content = "\\id GEN - Genesis\n\\toc1  The First Book of Moses \n\\toc2 Genesis\n\\toc3\tGen\n"
            . "\\c 1\n\\v 1 In the beginning.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertSame('The First Book of Moses', $imp->getBookMetas()[1]['name_long']);
        $this->assertSame('Genesis', $imp->getBookMetas()[1]['name']);
        $this->assertSame('Gen', $imp->getBookMetas()[1]['shortname']);
    }

    // -----------------------------------------------------------------------
    // \rem editorial remarks must not end a verse or bleed into its text
    // -----------------------------------------------------------------------

    public function testRemarkLinesDoNotBreakOrPolluteVerses(): void
    {
        $content = "\\id GEN - Genesis\n\\rem File-level editor note\n\\c 1\n"
            . "\\v 1 In the\n\\rem mid-verse editor note\nbeginning.\n\\v 2 And the earth.\n";
        [$Zip, $entry, $path] = $this->makeZip('01GEN.usfm', $content);

        $imp = $this->makeImporter();
        $imp->callZipImportHelper($Zip, $entry);
        $this->cleanup($Zip, $path);

        $this->assertCount(2, $imp->recorded);
        $this->assertSame('In the beginning.', $imp->recorded[0]['text']);
        $this->assertSame('And the earth.', $imp->recorded[1]['text']);
    }

    // -----------------------------------------------------------------------
    // checkUploadedFile: a corrupt zip must be reported as unopenable, not as
    // "contains no .usfm files"
    // -----------------------------------------------------------------------

    public function testCheckUploadedFileRejectsCorruptZip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        file_put_contents($path, 'this is not a zip archive');
        $File = new \Illuminate\Http\UploadedFile($path, 'corrupt.usfm.zip', null, null, true);

        $imp = $this->makeImporter();
        $result = $imp->checkUploadedFile($File);
        unlink($path);

        $this->assertFalse($result);
        $this->assertStringContainsString('unable to open ZIP', implode(' ', $imp->getErrors()));
    }

    // -----------------------------------------------------------------------
    // checkUploadedFile: copr.htm must be found even as the FIRST zip entry
    // (locateName() returns index 0, which is falsy)
    // -----------------------------------------------------------------------

    public function testCheckUploadedFileFindsCoprAtIndexZero(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        $Zip  = new ZipArchive();
        $Zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $Zip->addFromString('copr.htm', '<p>Public Domain</p>'); // index 0
        $Zip->addFromString('01GEN.usfm', "\\id GEN\n\\c 1\n\\v 1 In the beginning.\n");
        $Zip->close();
        $File = new \Illuminate\Http\UploadedFile($path, 'bible.usfm.zip', null, null, true);

        $imp = $this->makeImporter();
        $result = $imp->checkUploadedFile($File);
        unlink($path);

        $this->assertTrue($result);
        $this->assertSame('<p>Public Domain</p>', $imp->getBibleAttributes()['description']);
    }

    // -----------------------------------------------------------------------
    // Plain (non-zipped) .usfm file: a whole-Bible plaintext file must be
    // accepted by checkUploadedFile and parse via _importContents
    // -----------------------------------------------------------------------

    public function testCheckUploadedFileAcceptsPlainUsfmFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        file_put_contents($path, "\xEF\xBB\xBF\\id GEN - Genesis\n\\c 1\n\\v 1 In the beginning.\n");
        $File = new \Illuminate\Http\UploadedFile($path, 'whole_bible.usfm', null, null, true);

        $imp = $this->makeImporter();
        $result = $imp->checkUploadedFile($File);
        unlink($path);

        $this->assertTrue($result, implode(' ', $imp->getErrors()));
        $this->assertNull($imp->getBibleAttributes()['description']);
    }

    public function testCheckUploadedFileAcceptsPlainSfmFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        file_put_contents($path, "\\id MAT - Matthew\n\\c 1\n\\v 1 The book of the generation.\n");
        $File = new \Illuminate\Http\UploadedFile($path, 'MAT.SFM', null, null, true);

        $imp = $this->makeImporter();
        $result = $imp->checkUploadedFile($File);
        unlink($path);

        $this->assertTrue($result, implode(' ', $imp->getErrors()));
    }

    // -----------------------------------------------------------------------
    // checkUploadedFile: a .zip whose name does not contain "sfm" (e.g. a
    // Paratext "project.zip") must be accepted, not rejected on the filename
    // -----------------------------------------------------------------------

    public function testCheckUploadedFileAcceptsZipWithoutSfmInName(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        $Zip  = new ZipArchive();
        $Zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $Zip->addFromString('01GEN.usfm', "\\id GEN\n\\c 1\n\\v 1 In the beginning.\n");
        $Zip->close();
        $File = new \Illuminate\Http\UploadedFile($path, 'project.zip', null, null, true);

        $imp = $this->makeImporter();
        $result = $imp->checkUploadedFile($File);
        unlink($path);

        $this->assertTrue($result, implode(' ', $imp->getErrors()));
    }

    // -----------------------------------------------------------------------
    // checkUploadedFile: a file with an unsupported extension must be rejected,
    // even if its name happens to contain the substring "sfm"
    // -----------------------------------------------------------------------

    public function testCheckUploadedFileRejectsUnsupportedExtension(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        file_put_contents($path, "\\id GEN - Genesis\n\\c 1\n\\v 1 In the beginning.\n");
        $File = new \Illuminate\Http\UploadedFile($path, 'sfm_notes.txt', null, null, true);

        $imp = $this->makeImporter();
        $result = $imp->checkUploadedFile($File);
        unlink($path);

        $this->assertFalse($result);
        $this->assertStringContainsString('does not appear to be a usfm file', strtolower(implode(' ', $imp->getErrors())));
    }

    public function testCheckUploadedFileRejectsPlainFileWithoutIdMarkers(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'usfm_test_');
        file_put_contents($path, "This is just some plain text.\nNo USFM markers here.\n");
        $File = new \Illuminate\Http\UploadedFile($path, 'not_a_bible.usfm', null, null, true);

        $imp = $this->makeImporter();
        $result = $imp->checkUploadedFile($File);
        unlink($path);

        $this->assertFalse($result);
        $this->assertStringContainsString('no \id markers', implode(' ', $imp->getErrors()));
    }

    public function testPlainMultiBookContentImportsAllVersesOfAllBooks(): void
    {
        $content = "\xEF\xBB\xBF\\id GEN - Genesis\n\\ide UTF-8\n\\toc2 Genesis\n\\c 1\n"
            . "\\v 1 In the beginning.\n\\v 2 And the earth was without form.\n"
            . "\\id EXO - Exodus\n\\ide UTF-8\n\\toc2 Exodus\n\\c 1\n"
            . "\\v 1 Now these are the names.\n";

        $imp = $this->makeImporter();
        $result = $imp->callImportContents($content, 'whole_bible.usfm');

        $this->assertTrue($result);
        $this->assertCount(3, $imp->recorded);
        $this->assertSame([1, 1, 2], array_column($imp->recorded, 'book'));
        $this->assertSame('And the earth was without form.', $imp->recorded[1]['text']);
        $this->assertSame('Now these are the names.', $imp->recorded[2]['text']);
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

    // -----------------------------------------------------------------------
    // Character markers opening mid-word must not leave a space inside the word.
    // Per the USFM spec, the space after a non-closing marker delimits the marker
    // and belongs to it, not to the text.
    // -----------------------------------------------------------------------

    #[DataProvider('characterMarkerSpacingDataProvider')]
    public function testCharacterMarkerSpacing(string $input, string $expected): void
    {
        $imp = $this->makeImporter();

        $this->assertSame($expected, $imp->callFormatText($input));
    }

    public static function characterMarkerSpacingDataProvider(): array
    {
        return [
            'transliterated word opening mid-word' => [
                '\add wanda\add* ushikima, waungonyi, uchila, wa\tl linyu\tl* yamwaza yakuhosa,',
                '[wanda] ushikima, waungonyi, uchila, walinyu yamwaza yakuhosa,',
            ],
            'nested marker opening mid-word' => [
                'wa\+tl linyu\+tl* yamwaza',
                'walinyu yamwaza',
            ],
            'marker between whole words keeps the surrounding space' => [
                'the \nd LORD\nd* said',
                'the LORD said',
            ],
            'closing marker followed by a suffix' => [
                'the \nd LORD\nd*\'s house',
                'the LORD\'s house',
            ],
            'line marker at the start of the text leaves no leading space' => [
                '\q1 Blessed is the man',
                'Blessed is the man',
            ],
            'numbered marker mid-word' => [
                'wa\qs linyu\qs*',
                'walinyu',
            ],
            'footnote attached to a word' => [
                'in the beginning\f + \ft a note\f* God created',
                'in the beginning God created',
            ],
            'trailing marker leaves no trailing space' => [
                'First verse. \m',
                'First verse.',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // The same spacing rule must hold end-to-end through _importContents
    // -----------------------------------------------------------------------

    public function testMidWordCharacterMarkerImportsWithoutSpace(): void
    {
        $content = "\\id EXO - Exodus\n\\c 25\n"
            . "\\m\n"
            . "\\v 4 \\add wanda\\add* ushikima, waungonyi, uchila, wa\\tl linyu\\tl* yamwaza yakuhosa,\n";

        $imp = $this->makeImporter();
        $imp->callImportContents($content, '02EXO.usfm');

        $this->assertCount(1, $imp->recorded);
        $this->assertStringContainsString('walinyu', $imp->recorded[0]['text']);
        $this->assertStringNotContainsString('wa linyu', $imp->recorded[0]['text']);
    }

    // -----------------------------------------------------------------------
    // Nested character markers (\+add, \+wj) must produce the same markup as
    // their un-nested forms, not be stripped as unknown markup.
    // -----------------------------------------------------------------------

    #[DataProvider('nestedMarkerDataProvider')]
    public function testNestedMarkersKeepTheirMarkup(string $input, string $expected): void
    {
        $imp = $this->makeImporter();

        $this->assertSame($expected, $imp->callFormatText($input));
    }

    public static function nestedMarkerDataProvider(): array
    {
        return [
            'nested add inside wj' => [
                '\\wj text \\+add supplied\\+add* more\\wj*',
                '‹text [supplied] more›',
            ],
            'nested add alone matches un-nested add' => [
                'the \\+add supplied\\+add* word',
                'the [supplied] word',
            ],
            'nested wj inside a quotation' => [
                'he said \\+wj follow me\\+wj* to them',
                'he said ‹follow me› to them',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // Only a trailing parenthetical cross reference is removed; an inline
    // parenthetical must not truncate the rest of the verse.
    // -----------------------------------------------------------------------

    #[DataProvider('parentheticalReferenceDataProvider')]
    public function testParentheticalCrossReferenceHandling(string $input, string $expected): void
    {
        $imp = $this->makeImporter();

        $this->assertSame($expected, $imp->callFormatText($input));
    }

    public static function parentheticalReferenceDataProvider(): array
    {
        return [
            'trailing reference removed' => [
                'In the beginning God created (See Gen 1:1)',
                'In the beginning God created',
            ],
            'inline reference keeps the rest of the verse' => [
                'Some text (see 1:1) more text',
                'Some text (see 1:1) more text',
            ],
            'inline reference before a trailing reference' => [
                'A (see 2:2) and B (See 3:3)',
                'A (see 2:2) and B',
            ],
            'trailing parenthetical without a reference is kept' => [
                'Some text (an aside)',
                'Some text (an aside)',
            ],
            'reference outside parentheses is kept' => [
                'As it says in Gen 1:1, God created',
                'As it says in Gen 1:1, God created',
            ],
        ];
    }
}
