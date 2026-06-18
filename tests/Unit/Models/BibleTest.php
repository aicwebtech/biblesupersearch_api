<?php

namespace Tests\Unit\Models;

use App\Models\Bible;
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
}
