<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMasterIndicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('master_indices', function (Blueprint $table) {
            $table->increments('id');
            $table->tinyInteger('book')->unsigned();
            $table->tinyInteger('chapter')->unsigned();
            $table->tinyInteger('verse')->unsigned();
            $table->text('strongs')->nullable(); // Master strongs numbers.  Todo - populate from parsed Greek / Hebrew text. 
            $table->tinyInteger('standard')->default(0); // Is this index in the KJV 
            $table->index('book','ixb');
            $table->index('chapter','ixc');
            $table->index('verse','ixv');
            $table->index(['book', 'chapter','verse'], 'ixbcv'); // Composite index on b, c, v
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('master_indices');
    }
}
