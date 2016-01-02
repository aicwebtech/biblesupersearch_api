<?php

use Illuminate\Database\Seeder;
use App\Models\Books\Abs as Book;

class BookListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $languages = Config::get('bss_table_languages.books');

        if(env('IMPORT_FROM_V2', FALSE)) {
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
        else {
            // todo - import book lists from files
        }
    }
}
