<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('renderings', function (Blueprint $table) {
            $table->string('file_name')->nullable()->change();
            $table->string('meta_hash', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('renderings', function (Blueprint $table) {
            $table->string('file_name')->nullable(false)->change();
            $table->string('meta_hash', 32)->nullable(false)->change();
        });
    }
};
