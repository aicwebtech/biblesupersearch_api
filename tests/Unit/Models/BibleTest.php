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
}
