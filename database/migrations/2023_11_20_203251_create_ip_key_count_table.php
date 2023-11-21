<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_ip_key_count', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('key_id')->unsigned();
            $table->integer('ip_id')->unsigned();
            $table->date('date');
            $table->integer('count')->unsigned();
            $table->timestamps();
            $table->unique(['key_id', 'ip_id', 'date'], 'ixcv');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_ip_key_count');
    }
};
