<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EnhanceBiblesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bibles', function (Blueprint $table) {
            $table->tinyInteger('red_letter')->default(0)->unsigned()->after('strongs');
            $table->tinyInteger('paragraph')->default(0)->unsigned()->after('red_letter');
            $table->string('publisher', 100)->nullable()->after('year');
            $table->string('owner', 100)->nullable()->after('publisher');
            $table->integer('copyright_id')->nullable()->unsigned()->after('copyright');
            $table->integer('citation_limit')->default(0)->unsigned()->after('copyright_id')->comment('Number of verses that can be displayed at once, 0 = unlimited');
            $table->tinyInteger('restrict')->default(0)->unsigned()->after('citation_limit')->comment('restrict access to only local domains');
            $table->string('importer', 50)->nullable()->after('module_v2');
            $table->string('import_file')->nullable()->after('importer');
            $table->integer('hebrew_text_id')->nullable()->unsigned()->after('research');
            $table->integer('greek_text_id')->nullable()->unsigned()->after('hebrew_text_id');
            $table->integer('translation_type_id')->nullable()->unsigned()->after('greek_text_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bibles', function (Blueprint $table) {
            $table->dropColumn(['owner', 'publisher', 'citation_limit', 'red_letter', 'copyright_id', 'restict', 'paragraph', 'importer', 'import_file',
                'hebrew_text_id', 'greek_text_id', 'translation_type_id'
            ]);
        });
    }
}
