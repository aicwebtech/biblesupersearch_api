<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Database\Seeders\DatabaseSeeder;

class CreateShortcutsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $languages = Config::get('bss_table_languages.shortcuts');

        foreach($languages as $lang) {
            $tn = 'shortcuts_' . $lang;

            Schema::create($tn, function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('short1')->nullable();
                $table->string('short2')->nullable();
                $table->string('short3')->nullable();
                $table->mediumText('reference');
                $table->tinyInteger('display')->default(0)->unsigned();
                $table->timestamps();
            });

            $file  = 'shortcuts_' . $lang . '.sql';
            DatabaseSeeder::importSqlFile($file);
            DatabaseSeeder::setCreatedUpdated($tn);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $languages = Config::get('bss_table_languages.shortcuts');

        foreach($languages as $lang) {
            $tn = 'shortcuts_' . $lang;
            
            if(Schema::hasTable($tn)) {
                Schema::drop($tn);
            }
        }
    }
}
