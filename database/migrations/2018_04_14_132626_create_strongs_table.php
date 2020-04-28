<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Importers\Database as DatabaseImport;
use App\Models\StrongsDefinition as Model;

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

        DB::table('strongs_definitions')->truncate();

        // DatabaseImport::importSqlFile('strongs_definitions_en.sql');
        Model::migrateFromCsv();
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
