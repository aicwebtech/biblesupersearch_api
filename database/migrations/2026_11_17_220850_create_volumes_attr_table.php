<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Volume settings attached to another record: (type, module) names the record and value is
 * usually a volume module.  A Bible's default Strong's dictionary is
 * (type = 'bible', module = <Bible module>, attribute = 'default_strongs_dictionary').
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('volumes_attr', function (Blueprint $table) {
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';

            $table->increments('id');
            $table->string('module', 255);
            $table->string('type', 50);
            $table->string('attribute', 100);
            $table->string('value', 255);
            $table->unique(['type', 'module', 'attribute'], 'volumes_attr_tma');
            $table->index(['attribute', 'value'], 'volumes_attr_av');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volumes_attr');
    }
};
