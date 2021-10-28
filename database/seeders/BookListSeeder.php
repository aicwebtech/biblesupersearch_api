<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Books\BookAbstract as Book;

class BookListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        $languages = Config::get('bss_table_languages.books');

        // import book lists from files
        foreach($languages as $lang) {
            $file  = 'bible_books_' . $lang . '.sql';
            $table = 'books_' . $lang;
            DatabaseSeeder::importSqlFile($file);
            DatabaseSeeder::setCreatedUpdated($table);
        }
    }
}
