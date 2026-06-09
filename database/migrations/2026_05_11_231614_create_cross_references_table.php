<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\CrossReference as Model;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cross_references', function (Blueprint $table) {
            $table->id();

            $table->tinyInteger('from_book')->unsigned()->comment('Source reference book id (1-66)');
            $table->tinyInteger('from_chapter')->unsigned()->comment('Source reference chapter number');
            $table->tinyInteger('from_verse')->unsigned()->comment('Source reference verse number');

            $table->tinyInteger('to_book')->unsigned()->comment('Target reference book id (1-66)');
            $table->tinyInteger('to_chapter_start')->unsigned()->comment('Target range start chapter');
            $table->tinyInteger('to_verse_start')->unsigned()->comment('Target range start verse');
            $table->tinyInteger('to_chapter_end')->unsigned()->comment('Target range end chapter');
            $table->tinyInteger('to_verse_end')->unsigned()->comment('Target range end verse');

            $table->integer('votes')->default(0)->comment('Ranking votes from source file (supports negative values)');
            $table->timestamps();

            $table->index(['from_book', 'from_chapter', 'from_verse'], 'ix_cross_refs_from');
            $table->index(['to_book', 'to_chapter_start', 'to_verse_start'], 'ix_cross_refs_to_start');
        });

        // Model::migrateFromCsv();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cross_references');
    }
};
