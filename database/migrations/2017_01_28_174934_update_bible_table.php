<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBibleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bibles', function ($table) {
            $table->tinyInteger('official')->default(0)->unsigned()->after('enabled');
            $table->tinyInteger('research')->default(0)->unsigned()->after('official');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bibles', function ($table) {
            $table->dropColumn(['official', 'research']);
        });
    }
}
