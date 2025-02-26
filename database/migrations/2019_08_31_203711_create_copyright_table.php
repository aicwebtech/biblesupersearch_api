<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Bible;
use App\Models\Copyright as Model;
use Database\Seeders\DatabaseSeeder;

class CreateCopyrightTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('copyrights', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->string('cr_name', 100);
            $table->string('type', 100);
            $table->string('url', 255)->nullable();
            $table->text('desc');
            $table->text('comments')->nullable();
            $table->text('default_copyright_statement');
            $table->float('version', 6, 1)->default(1.0);
            $table->tinyInteger('download')->default(0)->unsigned()->comment('Whether to allow downloading of the Bible as a file.');
            $table->tinyInteger('external')->default(0)->unsigned()->comment('Whether to allow access to the Bible outside of the local domain.');
            $table->tinyInteger('permission_required')->default(0)->unsigned()->comment('Whether special permission is required to use in Bible SuperSearch.');
            $table->tinyInteger('attribution_required')->default(1)->unsigned()->comment('Whether attribution to the original content creator is required.');
            $table->tinyInteger('share_alike')->default(0)->unsigned()->comment('Whether modified versions must be shared under this same license.');
            $table->tinyInteger('non_commercial')->default(0)->unsigned()->comment('Whether permission is required for commercial use.');
            $table->tinyInteger('no_derivatives')->default(0)->unsigned()->comment('Only original copies can be copied and distributed. (Requires special permission to use in Bible SuperSearch');
            $table->integer('rank')->default(9999)->unsigned();
            $table->unique('cr_name', 'idxcr');
            $table->timestamps();
        });

        Schema::table('bibles', function (Blueprint $table) {
            $table->text('copyright_statement')->after('copyright_id');
            $table->string('url', 255)->after('copyright_statement')->nullable()->comment('URL to website for this translation, if it exists');
        });

        Bible::updateBibleTable(['copyright', 'copyright_id', 'copyright_statement', 'url']);

        // Model::migrateFromCsv();
        DatabaseSeeder::importSqlFile('copyrights.sql');
        DatabaseSeeder::setCreatedUpdated('copyrights');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('copyrights');

        Schema::table('bibles', function (Blueprint $table) {
            $table->dropColumn('copyright_statement');
            $table->dropColumn('url');
        });
    }
}
