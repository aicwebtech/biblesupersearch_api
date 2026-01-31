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
        Schema::create('bible_verses_audio', function (Blueprint $table) {
            $table->id();
            $table->string('module')->comment('Bible module');
            $table->string('file_name')->nullable()->comment('Internal file name for audio file');
            $table->string('source')->nullable()->comment('Source of the audio file: TTS API or uploaded');
            $table->string('voice')->nullable()->comment('Voice used for TTS generation');
            $table->tinyInteger('book')->unsigned();
            $table->tinyInteger('chapter')->unsigned();
            $table->tinyInteger('verse')->unsigned()->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bible_verses_audio');
    }
};
