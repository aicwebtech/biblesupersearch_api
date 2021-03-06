<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRenderingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('renderings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('renderer', 70)->comment('Simple class name of render class');
            $table->string('module')->comment('Bible module');
            $table->tinyInteger('custom')->default(0);
            $table->string('file_name');
            $table->float('version', 8, 1)->nullable()->comment('Version number off of the render class file.  Used to determine if a new render is needed.');
            $table->unsignedBigInteger('hits')->default(0)->comment('Number of downloads for this rendering for all time');
            $table->unsignedInteger('file_size')->nullable()->comment('Size of rendered file, in MB');
            $table->string('meta_hash', 32)->comment('MD5 Hash of the meta data and copyright statement as rendered into the file.');
            $table->dateTime('downloaded_at')->nullable()->comment('Last download time');
            $table->dateTime('rendered_at')->nullable();
            $table->unsignedInteger('rendered_duration')->nullable()->comment('Time it took to render the file, in seconds');
            $table->timestamps();
            $table->unique(['renderer','module']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('renderings');
    }
}
