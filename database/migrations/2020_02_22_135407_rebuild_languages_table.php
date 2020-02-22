<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Importers\Database as DatabaseImport;

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
            $table->string('iso_name', 255)->comment('ISO Language Name');
            $table->string('native_name', 255)->comment('Endonym');
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

        $map = [
            'name', 'iso_name', 'code', 'native_name', 'rtl', 'family', 'iso_639_1', 'iso_639_2', 'iso_639_2_b', 'iso_639_3_raw', 'notes'
        ];

        // $mig =  'set'.Illuminate\Support\Str::studly('iso_639_3_raw').'Attribute';
        // var_dump($mig);
        // die();

        DatabaseImport::importCSV('languages.csv', $map, '\App\Models\Language', 'code');
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
