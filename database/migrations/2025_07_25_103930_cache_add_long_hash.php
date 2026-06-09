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
            $table->string('hash_long', 32); // new
            $table->text('form_data');
            $table->tinyInteger('preserve')->default(0)->unsigned();
            $table->timestamps();
            $table->unique('hash', 'ux_cache_hash');
            $table->unique('hash_long', 'ux_cache_hash_long'); // new
        });

        // skip if using sqlite 
        if(DB::getDriverName() !== 'sqlite') {
            DB::update("UPDATE " . $pre . "cache_old SET hash_long = MD5(form_data)");
            DB::insert("INSERT IGNORE INTO " . $pre . "cache SELECT * FROM " . $pre . "cache_old");
        }

        Schema::dropIfExists('cache_old');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cache', function (Blueprint $table) {
            $table->dropColumn(['hash_long']);
            $table->dropUnique('ux_cache_hash_long');
        });

        Schema::dropIfExists('cache_old');
    }
};
