<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ip_access', function (Blueprint $table) {
            $table->integer('access_level_id')->default(2);
        });

        Schema::table('ip_access_log', function (Blueprint $table) {
            $table->integer('key_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ip_access', function (Blueprint $table) {
            $table->dropColumn(['access_level_id']);
        });        

        Schema::table('ip_access_log', function (Blueprint $table) {
            $table->dropColumn(['key_id']);
        });
    }
};
