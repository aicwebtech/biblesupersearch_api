<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Language as Model;

class RebuildLanguagesTable extends Migration
{
    // This removes and replaces the original languages table created in 2018_04_17_194851_create_languages_table.php

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('languages');
        
        Schema::create('languages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255)->comment('Display name (English)');
            $table->string('iso_name', 255)->comment('ISO Language Name - Raw');
            $table->string('native_name', 255)->comment('Endonym');
            $table->string('iso_endonym', 255)->comment('ISO Endonym - Raw');
            $table->string('family', 255)->comment('Language Family');
            $table->string('code', 3)->unique()->comment('2 or 3 character unique code.  Either ISO-369-1 or ISO-369-2');
            $table->tinyInteger('rtl')->default(0)->unsigned()->comment('Right to left');
            $table->string('iso_639_1', 2)->nullable()->unique()->comment('ISO-361-1 code if exists');
            $table->string('iso_639_2', 3)->nullable()->unique()->comment('ISO-361-2/T code if exists');
            $table->string('iso_639_2_b', 3)->nullable()->unique()->comment('ISO-361-2/B code if exists');
            $table->string('iso_639_3', 3)->nullable()->unique()->comment('ISO-361-3 code if exists');
            $table->string('iso_639_3_raw', 100)->nullable()->comment('ISO-361-3 raw code if exists');
            $table->text('notes')->nullable()->comment('Comments');
        });

        Model::migrateFromCsv();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('languages');
    }
}
