<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CrossReferencesMigrationTest extends TestCase
{
    protected static bool $migrationApplied = false;

    public function setUp(): void
    {
        parent::setUp();

        if(!self::$migrationApplied) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_05_11_231614_create_cross_references_table.php',
                '--force' => true,
                '--no-interaction' => true,
            ]);

            self::$migrationApplied = true;
        }
    }

    public function test_cross_references_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('cross_references'));

        $this->assertTrue(Schema::hasColumns('cross_references', [
            'id',
            'from_book',
            'from_chapter',
            'from_verse',
            'to_book',
            'to_chapter_start',
            'to_verse_start',
            'to_chapter_end',
            'to_verse_end',
            'votes',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_cross_references_table_accepts_signed_votes_and_ranges(): void
    {
        DB::table('cross_references')->insert([
            'from_book' => 1,
            'from_chapter' => 1,
            'from_verse' => 1,
            'to_book' => 19,
            'to_chapter_start' => 89,
            'to_verse_start' => 11,
            'to_chapter_end' => 89,
            'to_verse_end' => 12,
            'votes' => -38,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('cross_references', [
            'from_book' => 1,
            'from_chapter' => 1,
            'from_verse' => 1,
            'to_book' => 19,
            'to_chapter_start' => 89,
            'to_verse_start' => 11,
            'to_chapter_end' => 89,
            'to_verse_end' => 12,
            'votes' => -38,
        ]);
    }
}
