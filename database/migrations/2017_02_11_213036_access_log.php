<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AccessLog extends Migration
{
    private $db_table = 'ip_access_log';
    
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create($this->db_table, function (Blueprint $table) {
            $table->increments('id');
            $table->integer('ip_id')->unsigned();
            $table->date('date');
            $table->integer('count')->unsigned();
            $table->tinyInteger('limit_reached')->unsigned()->default(0);
            $table->timestamps();
            $table->unique(['ip_id', 'date'], 'ux_ip_access_log_ip_id_date');
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
}
