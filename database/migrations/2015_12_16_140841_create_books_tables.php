<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Database\Seeders\DatabaseSeeder;

class CreateBooksTables extends Migration
{
    // This migration is obsolete
    // Book tables are now added when a Bible in given language is installed or imported!


    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        //\App\Models\Books\BookAbstract::createBookTables();
        

        // $languages = \App\Models\Books\BookAbstract::getSupportedLanguages();
        
        // foreach($languages as $lang) {
        //     $lang_lc = strtolower($lang);
        //     $tn = 'books_' . $lang_lc;

        //     Schema::create($tn, function (Blueprint $table) {
        //         $table->increments('id');
        //         $table->string('name');
        //         $table->string('shortname');
        //         $table->string('matching1')->nullable();
        //         $table->string('matching2')->nullable();
        //         $table->timestamps();
        //     });

        //     $file  = 'bible_books_' . $lang_lc . '.sql';
        //     DatabaseSeeder::importSqlFile($file);
        //     DatabaseSeeder::setCreatedUpdated($tn);
        // }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // \App\Models\Books\BookAbstract::dropBookTables();


        // $languages = \App\Models\Books\BookAbstract::getSupportedLanguages();

        // foreach($languages as $lang) {
        //     $tn = 'books_' . strtolower($lang);
        //     Schema::dropifExists($tn);
        // }
    }
}
