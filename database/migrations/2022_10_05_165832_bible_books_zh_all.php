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
        // if(Schema::hasTable('books_zh_cn')) {
        //     return; // skip if we already have these new tables
        // }

        // $languages = ['zh_CN', 'zh_TW'];

        // // Nuke existing bible_books_zh and repopulate it
        // DB::table('books_zh')->truncate();
        // Book::migrateFromCsv('zh');

        // foreach($languages as $lang) {
        //     Book::createBookTable($lang);
        //     Book::migrateFromCsv($lang);
        // }
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
