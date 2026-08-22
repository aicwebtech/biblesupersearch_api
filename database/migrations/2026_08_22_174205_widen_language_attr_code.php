<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * language_attr.code was varchar(3), which cannot hold the regional codes the application
 * already ships book lists and model classes for - 'zh_tw' and 'zh_cn'. MySQL silently
 * truncated both to 'zh_', so the second one collided with the first on the ica unique key,
 * and Language::hasBookSupport('zh_tw') could never match the truncated row.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('language_attr', function (Blueprint $table) {
            // Was string('code', 3), NOT NULL, no default - part of the 'ica' unique key and
            // the 'ic' index, both of which survive a width change.
            $table->string('code', 12)->change();
        });

        // Any 'zh_' row is a truncation artifact: no language uses that code, and nothing wrote
        // a code longer than three characters before the column was widened.
        DB::table('language_attr')->where('code', 'zh_')->delete();
    }

    public function down()
    {
        Schema::table('language_attr', function (Blueprint $table) {
            $table->string('code', 3)->change();
        });
    }
};
