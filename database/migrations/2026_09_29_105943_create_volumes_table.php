<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Volumes: generic container for extra-Biblical content (Strong's dictionaries, etc).
 *
 * Columns mirror the `bibles` table, except:
 * - `type` is new; name, shortname and module are unique per type
 * - `language` replaces `lang_short`; the obsolete `lang` column is omitted
 * - `module_v2` is omitted, as it is no longer used
 * - `year` is nullable, matching the validation rules
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('volumes', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->string('type', 50);
            $table->string('name');
            $table->string('shortname');
            $table->string('module');
            $table->string('year')->nullable();
            $table->string('publisher', 100)->nullable();
            $table->string('owner', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('language', 10);
            $table->tinyInteger('copyright')->default(0)->unsigned();
            $table->integer('copyright_id')->nullable()->unsigned();
            $table->text('copyright_statement')->nullable();
            $table->string('url')->nullable();
            $table->integer('citation_limit')->default(0)->unsigned()->comment('Number of verses that can be displayed at once, 0 = unlimited');
            $table->tinyInteger('restrict')->default(0)->unsigned()->comment('restrict access to only local domains');
            $table->tinyInteger('italics')->default(0)->unsigned();
            $table->tinyInteger('strongs')->default(0)->unsigned();
            $table->tinyInteger('red_letter')->default(0)->unsigned();
            $table->tinyInteger('paragraph')->default(0)->unsigned();
            $table->tinyInteger('installed')->default(0)->unsigned();
            $table->tinyInteger('enabled')->default(0)->unsigned();
            $table->tinyInteger('official')->default(0)->unsigned();
            $table->tinyInteger('research')->default(0)->unsigned();
            $table->integer('hebrew_text_id')->nullable()->unsigned();
            $table->integer('greek_text_id')->nullable()->unsigned();
            $table->integer('translation_type_id')->nullable()->unsigned();
            $table->mediumInteger('rank')->default(1000)->unsigned();
            $table->string('module_version', 50)->nullable();
            $table->tinyInteger('needs_update')->default(0)->unsigned();
            $table->string('importer', 50)->nullable();
            $table->string('import_file')->nullable();
            $table->timestamps();
            $table->dateTime('installed_at')->nullable();
            $table->dateTime('module_updated_at')->nullable();
            $table->tinyInteger('audio_enable')->default(0)->unsigned();
            $table->tinyInteger('tts_enable')->default(0)->unsigned();
            $table->string('audio_structure', 100)->nullable()->comment('chapters, verses or both');
            $table->string('tts_api', 100)->nullable();
            $table->string('tts_voice')->nullable();
            $table->double('tts_speed')->nullable();
            $table->string('book_list', 300)->nullable();

            $table->unique(['type', 'module'], 'ux_volumes_type_module');
            $table->unique(['type', 'name'], 'ux_volumes_type_name');
            $table->unique(['type', 'shortname'], 'ux_volumes_type_shortname');
            $table->index('language', 'ix_volumes_language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volumes');
    }
};
