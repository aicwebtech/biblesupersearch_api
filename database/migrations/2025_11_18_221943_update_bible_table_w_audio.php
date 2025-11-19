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
        Schema::table('bibles', function (Blueprint $table) {
            $table->tinyInteger('audio_enable')->default(0)->unsigned();
            $table->tinyInteger('tts_enable')->default(0)->unsigned();
            $table->string('audio_structure', length: 100)->nullable()->comment('chapters, verses or mixed');
            $table->string('tts_api', length: 100)->nullable();
            $table->string('tts_voice')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bibles', function (Blueprint $table) {
            $table->dropColumn([
                'audio_enable',
                'tts_enable',
                'tts_api',
                'tts_voice',
            ]);
        });
    }
};
