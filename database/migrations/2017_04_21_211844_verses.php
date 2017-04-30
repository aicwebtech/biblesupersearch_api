<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

use App\Models\Bible;

class Verses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        $Bibles = Bible::all();

        foreach($Bibles as $Bible) {
            if(!($Bible->verses() instanceof App\Models\Verses\VerseStandard)) {
                continue;
            }

            $table = $Bible->verses()->getTable();

            if(Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    // Change id to a SIGNED, auto incrementing integer
                    $table->integer('id', TRUE)->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        $Bibles = Bible::all();

        foreach($Bibles as $Bible) {
            if(!($Bible->verses() instanceof App\Models\Verses\VerseStandard)) {
                continue;
            }

            $table = $Bible->verses()->getTable();

            if(Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    $table->increments('id')->change();
                });
            }
        }
    }
}
