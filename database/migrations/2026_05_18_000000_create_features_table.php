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
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            
            $table->string('identifier')->comment('Feature identifier (e.g., cross_references, strongs)');
            $table->string('language')->nullable()->comment('Language code (e.g., en, ru, es) or null for non-language-specific features');
            $table->boolean('installed')->default(false)->comment('Whether the feature is installed');
            
            $table->timestamps();
            
            $table->unique(['identifier', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('features');
    }
};
