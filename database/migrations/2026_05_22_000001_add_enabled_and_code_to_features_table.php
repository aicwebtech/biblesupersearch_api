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

        foreach (Feature::all() as $feature) {
            $definition = FeatureDefinitions::find($feature->identifier);
            $mode = $definition
                ? FeatureDefinitions::getLanguageMode($definition)
                : (($feature->language === null || $feature->language === '')
                    ? FeatureDefinitions::LANGUAGE_MODE_NONE
                    : FeatureDefinitions::LANGUAGE_MODE_MULTI);

            $feature->code = Feature::buildCode($feature->identifier, $feature->language, $mode);
            $feature->enabled = (bool)$feature->installed;
            $feature->save();
        }

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
