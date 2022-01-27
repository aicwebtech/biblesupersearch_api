<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use \App\Models\Bible;

class AddUpdatesToBibleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bibles', function (Blueprint $table) {
            $table->string('module_version', 50)->nullable()->after('module_v2')->comment('When module is up to date, this will equal app version.');
            $table->tinyInteger('needs_update')->default(0)->unsigned()->after('module_version');
            $table->dateTime('module_updated_at')->nullable()->after('installed_at')->comment('Last time the Bible text was updated from the module file.');
        });

        if(Schema::hasColumn('bibles', 'restict')) {
            Schema::table('bibles', function (Blueprint $table) {
                $table->renameColumn('restict', 'restrict'); // LOL
            });
        }

        $KjvStrongs = Bible::findByModule('kjv_strongs');

        if($KjvStrongs) {
            $KjvStrongs->module_version = '4.4.0';
            $KjvStrongs->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bibles', function (Blueprint $table) {
            $table->dropColumn(['module_version', 'needs_update', 'module_updated_at']);
            $table->renameColumn('restrict', 'restict');
        });
    }
}
