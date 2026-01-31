<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->double('tts_speed')->nullable();
        });

        Schema::table('bibles', function (Blueprint $table) {
            $table->double('tts_speed')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->dropColumn([
                'tts_speed',
            ]);
        });

        Schema::table('bibles', function (Blueprint $table) {
            $table->dropColumn([
                'tts_speed',
            ]);
        });
    }
};
