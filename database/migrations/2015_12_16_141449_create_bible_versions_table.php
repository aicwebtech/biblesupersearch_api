<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

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
            $table->timestamps();
        });

        if(env('IMPORT_FROM_V2', FALSE)) {
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
        Schema::drop('bibles');

        // to do - uninsstall all installed Bibles
    }
}
