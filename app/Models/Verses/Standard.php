<?php

namespace App\Models\Verses;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Bible;

class Standard extends Abs
{   
	public function setBible(Bible $Bible) {
		parent::setBible($Bible);
		
		if($this->hasClass) {
			$this->table = 'verses_' . $Bible->module;
		}
		else {
			$this->table = 'verses_' . $Bible->module;
		}
	}
	
	public function install() {
        Schema::create($this->table, function (Blueprint $table) {
            $table->increments('id');
            $table->tinyInteger('book')->unsigned();
            $table->tinyInteger('chapter')->unsigned();
            $table->tinyInteger('verse')->unsigned();
            $table->text('text');
            $table->text('italics')->nullable();
            $table->text('strongs')->nullable();
            $table->index('book','ixb');
            $table->index('chapter','ixc');
            $table->index('verse','ixv');
            //$table->index('text'); // Needs length - not supported in Laravel?
        });
        
        // todo - import records from text file
	}
	
	public function uninstall() {
		Schema::drop($this->table);
	}
}
