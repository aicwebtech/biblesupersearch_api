<?php

namespace Tests\Unit\Importers;

use App\Importers\RowLimitReadFilter;
use PHPUnit\Framework\TestCase;

class RowLimitReadFilterTest extends TestCase
{
    public function testReadsRowsUpToAndIncludingTheLimit(): void
    {
        $Filter = new RowLimitReadFilter(200);

        $this->assertTrue($Filter->readCell('A', 1));
        $this->assertTrue($Filter->readCell('A', 200));
        $this->assertTrue($Filter->readCell('IV', 200), 'The limit applies to rows, not columns');
    }

    public function testRejectsRowsBeyondTheLimit(): void
    {
        $Filter = new RowLimitReadFilter(200);

        $this->assertFalse($Filter->readCell('A', 201));
        $this->assertFalse($Filter->readCell('A', 31102));
    }

    public function testAStartRowLimitsTheReadToARange(): void
    {
        $Filter = new RowLimitReadFilter(400, 201);

        $this->assertFalse($Filter->readCell('A', 200));
        $this->assertTrue($Filter->readCell('A', 201));
        $this->assertTrue($Filter->readCell('A', 400));
        $this->assertFalse($Filter->readCell('A', 401));
    }
}
