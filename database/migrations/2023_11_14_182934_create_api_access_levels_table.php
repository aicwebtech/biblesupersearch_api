<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use App\Models\ApiAccessLevel;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('api_access_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('system_name')->nullable();
            $table->unsignedTinyInteger('can_edit')->default(0)->comment('RFU');
            $table->integer('limit')->nullable();
            $table->unsignedTinyInteger('statistics')->default(0);
            $table->unsignedTinyInteger('commentaries')->default(0);
            $table->unsignedTinyInteger('dictionaries')->default(0);
            $table->unsignedTinyInteger('special_4')->default(0)->comment('RFU');
            $table->unsignedTinyInteger('special_5')->default(0)->comment('RFU');
            $table->unsignedTinyInteger('special_6')->default(0)->comment('RFU');
            $table->unsignedTinyInteger('special_7')->default(0)->comment('RFU');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->_populate();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('api_access_levels');
    }

    private function _populate()
    {
        $records = [
            ['id' => 1, 'name' => 'None', 'system_name' => 'none'],
            ['id' => 2, 'name' => 'Basic', 'system_name' => 'basic',],
            ['id' => 3, 'name' => 'Keyed', 'system_name' => 'keyed', 'can_edit' => 1],
            [
                'id' => 4, 
                'name' => 'Full', 
                'system_name' => 'full', 
                'level' => 0,
                'statistics' => 1, 
                'commentaries' => 1, 
                'dictionaries' => 1,
                'special_4' => 1, 
                'special_5' => 1, 
                'special_6' => 1, 
                'special_7' => 1,
            ]
        ];

        Model::unguard();

        foreach($records as $r) {
            $Level = new ApiAccessLevel;
            $Level->fill($r);
            $Level->save();
        }

        Model::reguard();
       
    }
};
