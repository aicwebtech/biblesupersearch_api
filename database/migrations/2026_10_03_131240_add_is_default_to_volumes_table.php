<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the per-type default flag to volumes.  Named is_default: 'default' is a reserved word in MySQL.
 *
 * The legacy Strong's volume (strongs / en_orig) becomes the Strong's default when it is
 * installed and enabled and no default exists, so fresh installs answer v3 /strongs out of the box.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('volumes', function (Blueprint $table) {
            $table->tinyInteger('is_default')->default(0)->unsigned()->after('official');
            $table->index(['type', 'is_default'], 'ix_volumes_type_default');
        });

        $has_default = DB::table('volumes')->where('type', 'strongs')->where('is_default', 1)->exists();

        if(!$has_default) {
            DB::table('volumes')
                ->where('type', 'strongs')
                ->where('module', 'en_orig')
                ->where('installed', 1)
                ->where('enabled', 1)
                ->update(['is_default' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('volumes', function (Blueprint $table) {
            $table->dropIndex('ix_volumes_type_default');
            $table->dropColumn('is_default');
        });
    }
};
