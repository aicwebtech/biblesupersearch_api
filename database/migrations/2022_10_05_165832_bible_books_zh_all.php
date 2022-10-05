<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\DatabaseSeeder;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('books_zh_cn')) {
            return; // skip if we already have these new tables
        }

        $languages = ['zh_CN', 'zh_TW'];

        // Nuke existing bible_books_zh and repopulate it
        DB::table('books_zh')->truncate();
        DatabaseSeeder::importSqlFile('bible_books_zh.sql');
        DatabaseSeeder::setCreatedUpdated('books_zh');
        
        foreach($languages as $lang) {
            $lang_lc = strtolower($lang);
            $tn = 'books_' . $lang_lc;

            Schema::create($tn, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('shortname');
                $table->string('matching1')->nullable();
                $table->string('matching2')->nullable();
                $table->timestamps();
            });

            $file  = 'bible_books_' . $lang_lc . '.sql';
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
        // Nothing to do
        // Allow main book list migration handle reversal
    }
};
