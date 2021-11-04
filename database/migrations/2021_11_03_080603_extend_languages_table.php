<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Language as Model;
use App\Models\Bible;

class ExtendLanguagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('languages', function (Blueprint $table) {
            $table->string('native_name', 255)->nullable()->comment('Endonym')->change();
            $table->string('iso_endonym', 255)->nullable()->comment('ISO Endonym - Raw')->change();
            $table->string('family', 255)->nullable()->comment('Language Family')->change();
            $table->string('encoding', 100)->default('utf8');
        });

        Model::migrateFromCsv2();

        $EL = Model::findByCode('el');
        $EL->name = 'Greek, Modern (1453-)';
        $EL->save();

        // Give Greek bibles the correct code for ancient Greek
        Bible::where('lang_short', 'el')->update(['lang_short' => 'grc']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Model::whereNull('iso_639_1')->delete();

        $EL = Model::findByCode('el');
        $EL->name = 'Greek';
        $EL->save();

        Bible::where('lang_short', 'grc')->update(['lang_short' => 'el']);

        Schema::table('languages', function (Blueprint $table) {
            $table->string('native_name', 255)->comment('Endonym')->change();
            $table->string('iso_endonym', 255)->comment('ISO Endonym - Raw')->change();
            $table->string('family', 255)->comment('Language Family')->change();
            $table->dropColumn('encoding');
        });

    }
}
