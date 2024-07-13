<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Models\Language;
use App\Models\Bible;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('language_attr', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3);
            $table->string('attribute', 100);
            $table->string('value', 255);
            $table->unique(['code', 'attribute'], 'ica');
            $table->index('code', 'ic');
        });

        // Init languages on all installed bibles. This will populate flags into new language attr table

        $languages = Bible::where('installed', 1)->groupBy('lang_short')->pluck('lang_short');

        foreach($languages as $l) {
            $Lang = Language::findByCode($l);
            $Lang && $Lang->initLanguage();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('language_attr');
    }
};
