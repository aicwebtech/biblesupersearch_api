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
        Schema::rename('bible_verses_audio', 'bible_verses_audio_old');
    
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
            $table->index(['module'], 'module_idx');
            $table->unique(['module', 'book', 'chapter', 'verse'], 'module_bcv_unique');
        });

        $pre = DB::getTablePrefix();

        // Migrate data from old table to new table, removing duplicates, and renumbering primary keys

        $sql = "INSERT IGNORE INTO " . $pre . "bible_verses_audio (module, file_name, source, voice, book, chapter, verse, created_at, updated_at) ".
               "SELECT module, file_name, source, voice, book, chapter, verse, created_at, updated_at ".
               "FROM " . $pre . "bible_verses_audio_old GROUP BY module, book, chapter, verse";

        DB::insert($sql);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bible_verses_audio');
        Schema::rename('bible_verses_audio_old', 'bible_verses_audio');
    }
};
