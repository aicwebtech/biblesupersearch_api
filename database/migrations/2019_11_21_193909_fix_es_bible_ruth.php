<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use aicwebtech\BibleSuperSearch\Models\Books\Es as Books;

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

        if($Book) {        
            $Book->name = 'Rut';
            $Book->save();
        }
    }   

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $Book = Books::find(8);

        if($Book) {        
            $Book->name = 'Ruth';
            $Book->save();
        }
    }
}
