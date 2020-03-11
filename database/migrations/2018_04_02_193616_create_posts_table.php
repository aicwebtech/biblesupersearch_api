<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use aicwebtech\BibleSuperSearch\Models\Post;

class CreatePostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('key', 200)->nullable();
            $table->string('title', 200)->nullable();
            $table->text('description', 200)->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('key');
        });

        $TOS = new Post();
        $TOS->title = 'Terms of Service';
        $TOS->content = '(Add your Terms of Service here)';
        $TOS->key = 'tos';
        $TOS->save();

        $Privacy = new Post();
        $Privacy->title = 'Privacy Policy';
        $Privacy->content = '(Add your Privacy Policy here)';
        $Privacy->key = 'privacy';
        $Privacy->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('posts');
    }
}
