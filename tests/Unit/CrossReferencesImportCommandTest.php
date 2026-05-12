<?php

namespace Tests\Unit;

use App\Console\Commands\ImportCrossReferences;
use Tests\TestCase;

class CrossReferencesImportCommandTest extends TestCase
{
    public function test_parse_single_target_token(): void
    {
        $Command = new ImportCrossReferences();

        $parsed = $Command->parseTokens('Gen.1.1', 'Rev.22.13');

        $this->assertNotNull($parsed);
        $this->assertSame(1, $parsed['from_book']);
        $this->assertSame(1, $parsed['from_chapter']);
        $this->assertSame(1, $parsed['from_verse']);
        $this->assertSame(66, $parsed['to_book']);
        $this->assertSame(22, $parsed['to_chapter_start']);
        $this->assertSame(13, $parsed['to_verse_start']);
        $this->assertSame(22, $parsed['to_chapter_end']);
        $this->assertSame(13, $parsed['to_verse_end']);
    }

    public function test_parse_target_range_token(): void
    {
        $Command = new ImportCrossReferences();

        $parsed = $Command->parseTokens('Gen.1.1', 'Ps.89.11-Ps.89.12');

        $this->assertNotNull($parsed);
        $this->assertSame(19, $parsed['to_book']);
        $this->assertSame(89, $parsed['to_chapter_start']);
        $this->assertSame(11, $parsed['to_verse_start']);
        $this->assertSame(89, $parsed['to_chapter_end']);
        $this->assertSame(12, $parsed['to_verse_end']);
    }

    public function test_parse_rejects_cross_book_target_range(): void
    {
        $Command = new ImportCrossReferences();

        $parsed = $Command->parseTokens('Gen.1.1', 'Ps.89.11-Rev.1.1');

        $this->assertNull($parsed);
    }
}
