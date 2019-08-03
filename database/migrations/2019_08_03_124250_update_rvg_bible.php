<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Bible;

class UpdateRvgBible extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $RVG = Bible::findByModule('rvg');
        // Fix missing language data on RVG Module

        if($RVG && (strtotime($RVG->created_at) < strtotime('2019-08-03 12:00:00')) ) {
            $RVG->lang = 'Spanish';
            $RVG->lang_short = 'es';
            $RVG->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
