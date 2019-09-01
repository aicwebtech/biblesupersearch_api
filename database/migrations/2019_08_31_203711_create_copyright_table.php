<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCopyrightTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('copyrights', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->text('desc');
            $table->text('default_copyright_statement');
            $table->tinyInteger('download')->default(0)->unsigned()->comment('Whether to allow downloading of the Bible as a file.');
            $table->tinyInteger('external')->default(0)->unsigned()->comment('Whether to allow access to the Bible outside of the local domain.');
            $table->integer('rank')->default(9999)->unsigned();
            $table->timestamps();
        });

        Schema::table('bibles', function (Blueprint $table) {
            $table->text('copyright_statement')->after('copyright_id');
        });

        DatabaseSeeder::importSqlFile('copyrights.sql');
        DatabaseSeeder::setCreatedUpdated('copyrights');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('copyrights');

        Schema::table('bibles', function (Blueprint $table) {
            $table->dropColumn('copyright_statement');
        });
    }
}
