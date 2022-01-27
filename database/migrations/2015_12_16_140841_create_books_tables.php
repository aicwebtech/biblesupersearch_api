<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Database\Seeders\DatabaseSeeder;

class CreateBooksTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $languages = \App\Models\Books\BookAbstract::getSupportedLanguages();
        
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

            $file  = 'bible_books_' . $lang . '.sql';
            DatabaseSeeder::importSqlFile($file);
            DatabaseSeeder::setCreatedUpdated($tn);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $languages = \App\Models\Books\BookAbstract::getSupportedLanguages();

        foreach($languages as $lang) {
            $tn = 'books_' . $lang;
            Schema::dropifExists($tn);
        }
    }
}
