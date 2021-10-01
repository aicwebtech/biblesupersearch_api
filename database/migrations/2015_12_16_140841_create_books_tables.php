<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBooksTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $languages = Config::get('bss_table_languages.books');
        foreach($languages as $lang) {
            $tn = 'books_' . $lang;

            Schema::create($tn, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('shortname');
                $table->string('matching1')->nullable();
                $table->string('matching2')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $languages = Config::get('bss_table_languages.books');

        foreach($languages as $lang) {
            $tn = 'books_' . $lang;
            Schema::dropifExists($tn);
        }
    }
}
