<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBooksTables extends Migration
{
    protected $languages = array('en','es','de','ru','ro','fr','hu','ar','it','nl');

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach($this->languages as $lang) {
            $tn = 'books_' . $lang;

            Schema::create($tn, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('shortname');
                $table->string('matching1')->nullable();
                $table->string('matching2')->nullable();
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
        foreach($this->languages as $lang) {
            $tn = 'books_' . $lang;
            Schema::drop($tn);
        }
    }
}
