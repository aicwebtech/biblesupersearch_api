<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The legacy Strong's definitions table becomes a feature (strongs_legacy).
 *
 * An existing installation has always had the table populated, so its feature starts installed
 * and enabled.  A new installation no longer populates the table (see create_strongs_table), so
 * the table is empty here and the feature starts off.
 */
return new class extends Migration
{
    private const CODE = 'strongs_legacy';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $populated = DB::table('strongs_definitions')->exists();
        $now = date('Y-m-d H:i:s');

        $values = [
            'installed'  => $populated ? 1 : 0,
            'enabled'    => $populated ? 1 : 0,
            'updated_at' => $now,
        ];

        if(DB::table('features')->where('code', self::CODE)->exists()) {
            DB::table('features')->where('code', self::CODE)->update($values);
            return;
        }

        DB::table('features')->insert($values + [
            'code'       => self::CODE,
            'identifier' => self::CODE,
            'language'   => 'en',
            'created_at' => $now,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('features')->where('code', self::CODE)->delete();
    }
};
