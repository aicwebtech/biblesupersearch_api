<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Books\Es as Books;

class FixEsBibleRuth extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $Book = Books::find(8);
        $Book->name = 'Rut';
        $Book->save();
    }   

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $Book = Books::find(8);
        $Book->name = 'Ruth';
        $Book->save();
    }
}
