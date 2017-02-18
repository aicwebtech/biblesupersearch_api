<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateIpAddress extends Migration
{
    private $db_table = 'ip_access';
    
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table($this->db_table, function ($table) {
            $table->index('ip_address','ixip');
            $table->index('domain','ixd');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table($this->db_table, function ($table) {
            $table->dropIndex('ixip');
            $table->dropIndex('ixd');
        });
    }
}
