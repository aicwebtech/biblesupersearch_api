<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Bible;

class CreateBibleVersionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bibles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('shortname');
            $table->string('module')->unique();
            $table->string('year');
            $table->text('description')->nullable();
            $table->string('lang');
            $table->string('lang_short');
            $table->tinyInteger('copyright')->default(0)->unsigned();
            $table->tinyInteger('italics')->default(0)->unsigned();
            $table->tinyInteger('strongs')->default(0)->unsigned();
            $table->tinyInteger('installed')->default(0)->unsigned();
            $table->tinyInteger('enabled')->default(0)->unsigned();
            $table->mediumInteger('rank')->default(0)->unsigned();
            $table->string('module_v2')->unique()->nullable();
            $table->timestamps();
        });

        if(config('bss.import_from_v2')) {
            //echo('importing from v2' . PHP_EOL);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // uninsstall all installed Bibles
        $Bibles = Bible::all();

        foreach($Bibles as $Bible) {
            $Bible->uninstall();
        }

        Schema::drop('bibles');
    }
}
