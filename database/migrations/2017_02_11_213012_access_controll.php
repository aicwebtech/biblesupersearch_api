<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AccessControll extends Migration
{
    private $db_table = 'ip_access';
    
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create($this->db_table, function (Blueprint $table) {
            $table->increments('id');
            $table->ipAddress('ip_address');
            $table->string('domain', 255)->nullable();
            $table->integer('limit')->nullable()->comment('Daily access limit. NULL means use default, 0 means unlmited access');
            $table->index(['ip_address', 'domain'], 'ipd');
            $table->timestamps();
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
