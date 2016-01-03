<?php

namespace App\Models\Verses;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Bible;
use DB;

class Standard extends VerseAbstract {
    // Todo - prevent installation if already installed!
    public function install() {
        if (Schema::hasTable($this->table)) {
            return TRUE;
        }

        $in_console = (strpos(php_sapi_name(), 'cli') !== FALSE) ? TRUE : FALSE;

        Schema::create($this->table, function (Blueprint $table) {
            $table->increments('id');
            $table->tinyInteger('book')->unsigned();
            $table->tinyInteger('chapter')->unsigned();
            $table->tinyInteger('verse')->unsigned();
            $table->text('text');
            $table->text('italics')->nullable();
            $table->text('strongs')->nullable();
            $table->index('book', 'ixb');
            $table->index('chapter', 'ixc');
            $table->index('verse', 'ixv');
            $table->index(['book', 'chapter', 'verse'], 'ixbcv'); // Composite index on b, c, v
            //$table->index('text'); // Needs length - not supported in Laravel?
        });

        // If importing from V2, make sure v2 table exists
        if (env('IMPORT_FROM_V2', FALSE)) {
            $v2_table = 'bible_' . $this->Bible->module_v2;
            $res = DB::select("SHOW TABLES LIKE '" . $v2_table . "'");
            $v2_table_exists = (count($res)) ? TRUE : FALSE;
        }

        if (env('IMPORT_FROM_V2', FALSE) && $v2_table_exists) {
            if ($in_console) {
                echo(PHP_EOL . 'Importing Bible from V2: ' . $this->Bible->name . ' (' . $this->module . ')' . PHP_EOL);
            }

            // we use this to determine what the strongs / italics fileds are
            $v_test = DB::select("SELECT * FROM {$v2_table} ORDER BY `index` LIMIT 1");
            $strongs = $italics = 'NULL';

            $strongs = isset($v_test[0]->strongs) ? 'strongs' : $strongs;
            $italics = isset($v_test[0]->italics) ? 'italics' : $italics;
            $italics = isset($v_test[0]->map) ? 'map' : $italics;

            $prefix = DB::getTablePrefix();

            $sql = "
                INSERT INTO {$prefix}verses_{$this->module} (id, book, chapter, verse, text, italics, strongs)
                SELECT `index`, book, chapter, verse, text, {$italics}, {$strongs}
                FROM {$v2_table}
            ";

            DB::insert($sql);
        } 
        else {
            // todo - import records from text file
        }

        return TRUE;
    }

    public function uninstall() {
        if (Schema::hasTable($this->table)) {
            Schema::drop($this->table);
        }

        return TRUE;
    }
    
}
