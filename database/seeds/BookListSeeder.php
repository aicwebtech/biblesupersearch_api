<?php

use Illuminate\Database\Seeder;
use aicwebtech\BibleSuperSearch\Models\Books\BookAbstract as Book;

class BookListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run() {
        $languages = Config::get('bss_table_languages.books');

        if(config('bss.import_from_v2')) {
            return $this->_importFromV2($languages);
        }

        // import book lists from files
        foreach($languages as $lang) {
            $file  = 'bible_books_' . $lang . '.sql';
            $table = 'books_' . $lang;
            DatabaseSeeder::importSqlFile($file);
            DatabaseSeeder::setCreatedUpdated($table);
        }
    }

    private function _importFromV2($languages) {
        echo('Importing Books From V2' . PHP_EOL);

        foreach($languages as $lang) {
            $v2_table = 'bible_books_' . $lang;
            $books = DB::select("SELECT * FROM {$v2_table}");
            $class_name = Book::getClassNameByLanguage($lang);
            echo($lang . ' ');

            foreach($books as $b) {
                $shortname = explode(' ', $b->short);
                $shortname = array_shift($shortname);

                $Book = new $class_name();
                $Book->id = $b->number;
                $Book->name = $b->fullname;
                $Book->shortname = $shortname;
                $Book->matching1 = $b->short;
                $Book->save();
            }
        }

        echo(PHP_EOL);
    }
}
