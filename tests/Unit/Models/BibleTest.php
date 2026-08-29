<?php

namespace Tests\Unit\Models;

use App\Models\Bible;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BibleTest extends TestCase
{
    public function testEncodeBookListEntire(): void
    {
        $this->assertSame('entire', Bible::encodeBookList(range(1, 66)));
    }

    public function testEncodeBookListOT(): void
    {
        $this->assertSame('ot', Bible::encodeBookList(range(1, 39)));
    }

    public function testEncodeBookListNT(): void
    {
        $this->assertSame('nt', Bible::encodeBookList(range(40, 66)));
    }

    public function testEncodeBookListFullOTPlusPartialNT(): void
    {
        $books = array_merge(range(1, 39), [45, 46]);
        $this->assertSame('ot,45,46', Bible::encodeBookList($books));
    }

    public function testEncodeBookListPartialOTPlusFullNT(): void
    {
        $books = array_merge([1, 2], range(40, 66));
        $this->assertSame('1,2,nt', Bible::encodeBookList($books));
    }

    public function testEncodeBookListArbitraryPartial(): void
    {
        $this->assertSame('1,5,40,44', Bible::encodeBookList([1, 5, 40, 44]));
    }

    public function testEncodeBookListEmpty(): void
    {
        $this->assertSame('', Bible::encodeBookList([]));
    }

    public function testDecodeBookListEntire(): void
    {
        $this->assertSame(range(1, 66), Bible::decodeBookList('entire'));
    }

    public function testDecodeBookListOT(): void
    {
        $this->assertSame(range(1, 39), Bible::decodeBookList('ot'));
    }

    public function testDecodeBookListNT(): void
    {
        $this->assertSame(range(40, 66), Bible::decodeBookList('nt'));
    }

    public function testDecodeBookListFullOTPlusPartialNT(): void
    {
        $this->assertSame(array_merge(range(1, 39), [45, 46]), Bible::decodeBookList('ot,45,46'));
    }

    public function testDecodeBookListArbitraryPartial(): void
    {
        $this->assertSame([1, 5, 40, 44], Bible::decodeBookList('1,5,40,44'));
    }

    public function testDecodeBookListSortsAndDedupes(): void
    {
        $this->assertSame([1, 5, 40], Bible::decodeBookList('40,1,5,1'));
    }

    public function testDecodeBookListEmpty(): void
    {
        $this->assertSame([], Bible::decodeBookList(''));
    }

    #[DataProvider('encodeRoundTripProvider')]
    public function testEncodeDecodeRoundTrip(array $books): void
    {
        $this->assertSame($books, Bible::decodeBookList(Bible::encodeBookList($books)));
    }

    public static function encodeRoundTripProvider(): array
    {
        return [
            'entire'      => [range(1, 66)],
            'ot'          => [range(1, 39)],
            'nt'          => [range(40, 66)],
            'partial'     => [[1, 5, 40, 44]],
            'ot + partial'=> [array_merge(range(1, 39), [45, 46])],
            'empty'       => [[]],
        ];
    }

    public function testMergeBookListsOTPlusNTYieldsEntire(): void
    {
        $this->assertSame(range(1, 66), Bible::mergeBookLists(['nt', 'ot']));
    }

    public function testMergeBookListsEntireYieldsAllBooks(): void
    {
        $this->assertSame(range(1, 66), Bible::mergeBookLists(['entire', 'nt']));
    }

    public function testMergeBookListsNTOnly(): void
    {
        $this->assertSame(range(40, 66), Bible::mergeBookLists(['nt', 'nt']));
    }

    public function testMergeBookListsPartialUnionDedupesAndSorts(): void
    {
        $this->assertSame([1, 5, 40, 44], Bible::mergeBookLists(['5,1,40', '44,1,5']));
    }

    public function testMergeBookListsEmpty(): void
    {
        $this->assertSame([], Bible::mergeBookLists(['', '']));
    }

    /**
     * Real-world layout: the 'tyndale' Bible has Genesis-Deuteronomy (1-5), Jonah (32),
     * and the full New Testament. Its stored book_list is '1,2,3,4,5,32,nt'.
     */
    public function testDecodeBookListRealWorldTyndale(): void
    {
        $expected = array_merge([1, 2, 3, 4, 5, 32], range(40, 66));
        $this->assertSame($expected, Bible::decodeBookList('1,2,3,4,5,32,nt'));
    }

    public function testEncodeBookListRealWorldTyndale(): void
    {
        $books = array_merge([1, 2, 3, 4, 5, 32], range(40, 66));
        $this->assertSame('1,2,3,4,5,32,nt', Bible::encodeBookList($books));
    }

    public function testMergeTyndalePlusFullOTYieldsEntire(): void
    {
        // Partial-OT + NT Bible merged with a full-OT Bible (e.g. wlc) covers all 66 books.
        $this->assertSame(range(1, 66), Bible::mergeBookLists(['1,2,3,4,5,32,nt', 'ot']));
    }

    public function testMergeTyndalePlusNtOnlyIsUnchanged(): void
    {
        // Merging tyndale with an NT-only Bible (e.g. tr) adds nothing new.
        $expected = array_merge([1, 2, 3, 4, 5, 32], range(40, 66));
        $this->assertSame($expected, Bible::mergeBookLists(['1,2,3,4,5,32,nt', 'nt']));
    }

    public function testMergePartialOverlappingOldTestaments(): void
    {
        // Two partial-OT Bibles with overlapping books are unioned, deduped and sorted.
        $this->assertSame([1, 2, 5, 32], Bible::mergeBookLists(['1,2,32', '5,32,1']));
    }

    /**
     * Builds an unsaved Bible. Everything below reads attributes or static paths only.
     *
     * @param array<string, mixed> $attributes
     */
    private function makeBible(array $attributes = []): Bible
    {
        $bible = new Bible();

        foreach ($attributes as $key => $value) {
            $bible->{$key} = $value;
        }

        return $bible;
    }

    // -----------------------------------------------------------------------
    // Module file export format
    // -----------------------------------------------------------------------

    /**
     * The export field order is the module file format. The source warns that new fields must
     * be appended and existing ones never reordered, or previously exported modules stop
     * importing - so the order is pinned here exactly.
     */
    public function testExportFieldOrderIsFrozen(): void
    {
        $this->assertSame(
            ['book', 'chapter', 'verse', 'text', 'italics', 'strongs'],
            Bible::getExportFields()
        );
    }

    public function testExportDelimiterIsFrozen(): void
    {
        $this->assertSame('|', Bible::getExportDelimiter());
    }

    /**
     * The delimiter must not be a character the export fields themselves contain, or a row
     * could not be split back apart.
     */
    public function testTheDelimiterDoesNotAppearInAnyFieldName(): void
    {
        foreach (Bible::getExportFields() as $field) {
            $this->assertStringNotContainsString(Bible::getExportDelimiter(), $field);
        }
    }

    // -----------------------------------------------------------------------
    // Module file locations
    // -----------------------------------------------------------------------

    public function testModuleFileNameIsTheModuleWithAZipExtension(): void
    {
        $this->assertSame('kjv.zip', $this->makeBible(['module' => 'kjv'])->getModuleFileName());
    }

    /**
     * Official modules are versioned in git under bibles/modules; unofficial ones are kept
     * out of it under bibles/unofficial. Filing one in the wrong place would either commit a
     * third-party Bible or lose an official one.
     */
    public function testAnOfficialModuleIsFiledUnderModules(): void
    {
        $bible = $this->makeBible(['module' => 'kjv', 'official' => 1]);

        $this->assertStringEndsWith('bibles/modules/kjv.zip', $bible->getModuleFilePath());
    }

    public function testAnUnofficialModuleIsFiledUnderUnofficial(): void
    {
        $bible = $this->makeBible(['module' => 'somebible', 'official' => 0]);

        $this->assertStringEndsWith('bibles/unofficial/somebible.zip', $bible->getModuleFilePath());
    }

    public function testTheShortModulePathIsRelativeToTheProjectRoot(): void
    {
        $this->assertSame('bibles/modules/', Bible::getModulePath(true));
        $this->assertSame('bibles/unofficial/', Bible::getUnofficialModulePath(true));
    }

    public function testTheLongModulePathIsAbsolute(): void
    {
        $this->assertStringStartsWith('/', Bible::getModulePath());
        $this->assertStringEndsWith('bibles/modules/', Bible::getModulePath());
    }

    public function testTheDedicatedShortPathHelpersMatchTheFlaggedForm(): void
    {
        $this->assertSame(Bible::getModulePath(true), Bible::getModulePathShort());
        $this->assertSame(Bible::getUnofficialModulePath(true), Bible::getUnofficialModulePathShort());
    }

    // -----------------------------------------------------------------------
    // Copyright statement
    // -----------------------------------------------------------------------

    public function testTheCopyrightStatementIsTrimmedOnWrite(): void
    {
        $bible = $this->makeBible(['copyright_statement' => "  Public domain.  \n"]);

        $this->assertSame('Public domain.', $bible->getAttributes()['copyright_statement']);
    }

    /**
     * A null statement is normalised to an empty string rather than stored as null, so the
     * column has one representation of "unset".
     */
    public function testANullCopyrightStatementBecomesAnEmptyString(): void
    {
        $bible = $this->makeBible(['copyright_statement' => null]);

        $this->assertSame('', $bible->getAttributes()['copyright_statement']);
    }

    public function testAnExplicitCopyrightStatementIsUsedAsIs(): void
    {
        $bible = $this->makeBible(['copyright_statement' => 'Used by permission.']);

        $this->assertSame('Used by permission.', $bible->getCopyrightStatement());
    }

    /**
     * With no statement and no copyright record, the description stands in - so a Bible never
     * renders a blank copyright line.
     */
    public function testTheDescriptionStandsInWhenThereIsNoCopyrightRecord(): void
    {
        $bible = $this->makeBible([
            'copyright_statement' => '',
            'copyright_id'        => null,
            'description'         => 'A public domain translation.',
        ]);

        $this->assertSame('A public domain translation.', $bible->getCopyrightStatement());
    }

    // -----------------------------------------------------------------------
    // Downloadability
    // -----------------------------------------------------------------------

    /**
     * Both guards short-circuit before the copyright relation is consulted, so they hold with
     * no database behind them. A restricted Bible must never be offered for download.
     */
    public function testARestrictedBibleIsNotDownloadable(): void
    {
        $this->assertFalse($this->makeBible(['restrict' => 1, 'copyright_id' => 2])->isDownloadable());
    }

    public function testABibleWithNoCopyrightRecordIsNotDownloadable(): void
    {
        $this->assertFalse($this->makeBible(['restrict' => 0, 'copyright_id' => null])->isDownloadable());
    }
}
