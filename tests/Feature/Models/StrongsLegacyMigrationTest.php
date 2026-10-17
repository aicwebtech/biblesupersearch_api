<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\Volume;
use App\Models\VolumeTypes\Strongs;
use App\Models\VolumeContent\StrongsContent;
use Illuminate\Support\Facades\DB;

/**
 * Checks the result of the migrate_legacy_strongs_to_volumes migration.  Read only: no content
 * data is written, except for the throwaway fixture volume in testImportLegacyIntoAFixture().
 */
class StrongsLegacyMigrationTest extends TestCase
{
    protected function enOrig(): Strongs
    {
        $Volume = Strongs::findByModule('en_orig');
        $this->assertNotNull($Volume, 'strongs / en_orig volume is missing');

        return $Volume;
    }

    public function testVolumeIsInstalled(): void
    {
        $Volume = $this->enOrig();

        $this->assertSame(1, (int) $Volume->installed);
        $this->assertSame('en', $Volume->language);
        $this->assertSame('cn_stro_en_orig', $Volume->content()->getTable());
    }

    public function testEveryLegacyRowIsCopied(): void
    {
        $table = $this->enOrig()->content()->getTable();

        $legacy = DB::table(StrongsContent::LEGACY_TABLE)->orderBy('number')->pluck('number')->all();
        $copied = DB::table($table)->orderBy('number')->pluck('number')->all();

        $this->assertNotEmpty($legacy);
        $this->assertSame($legacy, $copied);
    }

    public function testSpotChecks(): void
    {
        $table = $this->enOrig()->content()->getTable();
        $rows  = DB::table($table)->whereIn('number', ['G2', 'H1', 'H8680', 'H6220'])->get()->keyBy('number');

        $this->assertSame("Aaro\u{0304}n", $rows['G2']->transliteration);
        $this->assertSame("\u{0391}\u{0313}\u{03B1}\u{03C1}\u{03C9}\u{0301}\u{03BD}", $rows['G2']->root_word);
        $this->assertSame(0, (int) $rows['G2']->is_special);

        $this->assertSame("\u{05D0}\u{05D1}", $rows['H1']->root_word);

        $this->assertSame(1, (int) $rows['H8680']->is_special);
        $this->assertStringContainsString('<b>Stem:</b> Aphel', $rows['H8680']->definition);
        $this->assertStringNotContainsString('<b>Count:</b>', $rows['H8680']->definition);

        $this->assertNull($rows['H6220']->definition);
    }

    public function testNoEntitiesRemainInTheDecodedColumns(): void
    {
        $table = $this->enOrig()->content()->getTable();

        $encoded = DB::table($table)
            ->where(fn($q) => $q->where('root_word', 'like', '%&#%')
                ->orWhere('transliteration', 'like', '%&#%')
                ->orWhere('pronunciation', 'like', '%&#%'))
            ->count();

        $this->assertSame(0, $encoded);
    }

    public function testSpecialRowsAreTheTvmRows(): void
    {
        $table = $this->enOrig()->content()->getTable();

        $tvm = DB::table(StrongsContent::LEGACY_TABLE)
            ->where(fn($q) => $q->whereNull('entry')->orWhere('entry', ''))
            ->whereNotNull('tvm')->where('tvm', '<>', '')
            ->count();

        $this->assertSame($tvm, DB::table($table)->where('is_special', 1)->count());
    }

    /**
     * Runs the import into a throwaway volume, so batching is exercised without touching en_orig
     */
    public function testImportLegacyIntoAFixture(): void
    {
        $Volume = NULL;

        try {
            $suffix = bin2hex(random_bytes(3));

            $Volume = new Strongs();
            $Volume->module    = 'vol_legacy_' . $suffix;
            $Volume->name      = 'Legacy Import Fixture ' . $suffix;
            $Volume->shortname = 'LegImp ' . $suffix;
            $Volume->language  = 'en';
            $Volume->save();

            $this->assertTrue($Volume->install());

            $count = $Volume->content()->importLegacy();

            $this->assertSame(DB::table(StrongsContent::LEGACY_TABLE)->count(), $count);
            $this->assertSame($count, DB::table($Volume->content()->getTable())->count());
        }
        finally {
            if($Volume) {
                $Volume->content()->uninstall();
                $Volume->forceDelete();
            }
        }
    }
}
