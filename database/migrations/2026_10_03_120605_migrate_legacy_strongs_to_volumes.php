<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Copyright;
use App\Models\VolumeTypes\Strongs;

/**
 * Copies the legacy strongs_definitions table into the volumes system, as the strongs / en_orig
 * volume (table cn_stro_en_orig).  See StrongsContent::mapLegacyRow() for the mapping.
 *
 * The legacy table is left in place: the API reads it until BSS-152 phase 3.
 * Safe to re-run: an existing volume, installed table or populated table is left alone.
 */
return new class extends Migration
{
    private const MODULE = 'en_orig';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $Volume = Strongs::findByModule(self::MODULE);

        if(!$Volume) {
            $Volume = new Strongs([
                'name'          => 'Strong\'s Exhaustive Concordance',
                'shortname'     => 'Strong\'s',
                'module'        => self::MODULE,
                'language'      => 'en',
                'year'          => '1890',
                'copyright_id'  => Copyright::where('cr_name', 'public_domain')->value('id'),
                'official'      => 1,
            ]);

            $Volume->save();
        }

        if(!$Volume->installed && !$Volume->install(FALSE, TRUE)) {
            throw new \RuntimeException('Could not install the Strong\'s volume: ' . implode('; ', $Volume->getErrors()));
        }

        $Content = $Volume->content();

        if(!DB::table($Content->getTable())->exists()) {
            $Content->importLegacy();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $Volume = Strongs::findByModule(self::MODULE);

        if(!$Volume) {
            return;
        }

        if($Volume->installed) {
            $Volume->uninstall();
        }

        $Volume->content()->uninstall(); // in case the table outlived the installed flag
        $Volume->forceDelete();
    }
};
