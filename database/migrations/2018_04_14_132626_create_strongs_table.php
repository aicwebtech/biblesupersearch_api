<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStrongsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('strongs_definitions', function ($table) {
            $table->text('root_word')->nullable()->after('number');
            $table->string('transliteration', 255)->nullable()->after('root_word');
            $table->string('pronunciation', 255)->nullable()->after('transliteration');
            $table->text('tvm')->nullable()->after('pronunciation');
            $table->text('entry')->nullable()->change();
            $table->unique('number', 'snum');
        });

        // Left empty: the legacy definitions are the optional strongs_legacy feature, installed
        // from the Features page.  Existing installations ran this before that change, and keep them.
        DB::table('strongs_definitions')->truncate();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('strongs_definitions', function ($table) {
            $table->dropColumn(['root_word', 'transliteration', 'pronunciation', 'tvm']);
            $table->dropIndex('snum');
            $table->text('entry')->change();
        });
    }
}
