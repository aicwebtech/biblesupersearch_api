<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Books\BookAbstract as Book;
use Database\Seeders\DatabaseSeeder;

class CreateBooksTableHindi extends Migration
{
    private $languages = ['hi', 'pt', 'ja'];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach($this->languages as $lang) {
            $table = 'books_' . $lang;

            if(Schema::hasTable($table)) {
                continue;
            }

            Book::createBookTable($lang);
            Book::migrateFromCsv($lang);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Do nothing, handled by main books migration
    }
}
