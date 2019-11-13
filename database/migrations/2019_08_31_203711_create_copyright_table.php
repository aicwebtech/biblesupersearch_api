<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Bible;

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
            $table->string('cr_name', 100); // NEW
            $table->string('type', 100); // NEW
            $table->string('url', 255)->nullable(); // NEW
            $table->text('desc');
            $table->text('comments')->nullable(); // NEW
            $table->text('default_copyright_statement');
            $table->float('version', 6, 1)->default(1.0); // NEW
            $table->tinyInteger('download')->default(0)->unsigned()->comment('Whether to allow downloading of the Bible as a file.');
            $table->tinyInteger('external')->default(0)->unsigned()->comment('Whether to allow access to the Bible outside of the local domain.');
            $table->tinyInteger('permission_required')->default(0)->unsigned()->comment('Whether special permission is required to use in Bible SuperSearch.'); // NEW
            $table->tinyInteger('attribution_required')->default(1)->unsigned()->comment('Whether attribution to the original content creator is required.'); // NEW
            $table->tinyInteger('share_alike')->default(0)->unsigned()->comment('Whether modified versions must be shared under this same license.'); // NEW
            $table->tinyInteger('non_commercial')->default(0)->unsigned()->comment('Whether permission is required for commercial use.'); // NEW
            $table->tinyInteger('no_derivatives')->default(0)->unsigned()->comment('Only original copies can be copied and distributed. (Requires special permission to use in Bible SuperSearch'); // NEW
            $table->integer('rank')->default(9999)->unsigned();
            $table->unique('cr_name'); // NEW
            $table->timestamps();
        });

        Schema::table('bibles', function (Blueprint $table) {
            $table->text('copyright_statement')->after('copyright_id');
            $table->string('url')->after('copyright_statement')->nullable()->comment('URL to website for this translation, if it exists');
        });

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
        });
    }
}
