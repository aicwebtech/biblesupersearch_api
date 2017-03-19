<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class BibleBooksZh extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tn = 'books_zh';

        if(!Schema::hasTable($tn)) {
            Schema::create($tn, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('shortname');
                $table->string('matching1')->nullable();
                $table->string('matching2')->nullable();
                $table->timestamps();
            });

            // Now, we call the seed as part of the migration
            Artisan::call('db:seed', [
                '--class' => BookListSeeder::class,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tn = 'books_zh';
        Schema::drop($tn);
    }
}
