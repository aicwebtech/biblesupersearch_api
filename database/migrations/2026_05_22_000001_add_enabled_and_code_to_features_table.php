<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Features\FeatureDefinitions;
use App\Models\Feature;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->boolean('enabled')->default(false)->after('installed');
        });

        Schema::table('features', function (Blueprint $table) {
            $table->unique('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('features', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'enabled']);
        });
    }
};
