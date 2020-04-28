<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Books\BookAbstract as Book;

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

            Schema::create($table, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('shortname');
                $table->string('matching1')->nullable();
                $table->string('matching2')->nullable();
                $table->timestamps();
            });

            $file  = 'bible_books_' . $lang . '.sql';
            $table = 'books_' . $lang;
            DatabaseSeeder::importSqlFile($file);
            DatabaseSeeder::setCreatedUpdated($table);
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
            Schema::dropIfExists('books_' . $lang);
        }
    }
}
