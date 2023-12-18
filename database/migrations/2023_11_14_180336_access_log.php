<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private $db_table = 'api_key_access_log';
    
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create($this->db_table, function (Blueprint $table) {
            $table->increments('id');
            $table->integer('key_id')->unsigned();
            $table->date('date');
            $table->integer('count')->unsigned();
            $table->tinyInteger('limit_reached')->unsigned()->default(0);
            $table->timestamps();
            $table->unique(['key_id', 'date'], 'ixcv');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        if (Schema::hasTable($this->db_table)) {
            Schema::drop($this->db_table);
        }
    }
};
