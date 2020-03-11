<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use aicwebtech\BibleSuperSearch\Importers\Database as DatabaseImport;

class CreateLanguagesTable extends Migration
{
    // The languages table created here has been replaced by 2020_02_22_135407_rebuild_languages_table.php

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('languages', function (Blueprint $table) {
        //     $table->increments('id');
        //     $table->string('name', 100);
        //     $table->string('code', 2)->unique();
        // });

        DatabaseImport::importSqlFile('languages.sql');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::drop('languages');
    }
}
