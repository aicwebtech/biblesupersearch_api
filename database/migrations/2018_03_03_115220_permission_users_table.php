<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use aicwebtech\BibleSuperSearch\User;

class PermissionUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('access_level')->default(10)->unsigned();
            $table->text('comments')->nullable();
            $table->softDeletes();
        });

        // Existing users get full permissions of 100
        $Users = User::all();

        $Users->each(function($User) {
            $User->access_level = 100;
            $User->save();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['access_level', 'comments', 'deleted_at']);
        });
    }
}
