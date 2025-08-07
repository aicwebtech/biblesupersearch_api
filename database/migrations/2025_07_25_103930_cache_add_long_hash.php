<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\CacheManager;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $pre = DB::getTablePrefix();

        Schema::table('cache', function (Blueprint $table) {
            $table->string('hash_long', 32)->after('hash');
        });

        Schema::rename('cache', 'cache_old');

        Schema::create('cache', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('hash', 20);
            $table->string('hash_long', 32);
            $table->text('form_data');
            $table->tinyInteger('preserve')->default(0)->unsigned();
            $table->timestamps();
            $table->unique('hash', 'idh');
            $table->unique('hash_long', 'idh_long');
        });
        
        DB::update("UPDATE " . $pre . "cache_old SET hash_long = MD5(form_data)");

        DB::insert("INSERT IGNORE INTO " . $pre . "cache SELECT * FROM " . $pre . "cache_old");

        // Ensure the new column is unique
        // Schema::table('cache', function (Blueprint $table) {
        //     $table->string('hash_long', 32)->unique()->change();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cache', function (Blueprint $table) {
            $table->dropColumn(['hash_long']);
        });
    }
};
