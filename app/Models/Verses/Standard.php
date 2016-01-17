<?php

namespace App\Models\Verses;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Bible;
use App\Passage;
use App\Search;
use DB;

class Standard extends VerseAbstract {
    /**  
     * Processes and executes the Bible search query
     * 
     * @param array $Passages Array of App/Passage instances, represents the passages requested, if any
     * @param App/Search $Search App/Search instance, reporesenting the search keywords, if any
     * @param array $parameters Search parameters - user input
     * @return array $Verses array of Verses instances (found verses)
     */
    public static function getSearch($Passages = NULL, $Search = NULL, $parameters = array()) {
        $where = $Verses = array();
        $Verse = new static;
        $table = $Verse->getTable();
        $Query = static::select('id','book','chapter','verse','text');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');
        
        if($Passages) {
            $pquery = static::_buildPassageQuery($Passages);
            $Query->whereRaw($pquery);
        }
        
        if($Search) {
            $where[] = static::_buildSearchQuery($Search, $parameters);
        }
        
        
        $Verses = $Query->get();
        //$Verses = $Verse->select('book','chapter','verse','text')->take(10);
        
        //$Verses = DB::table($table)->select('book','chapter','verse','text')->take(10)->get();
        //$Verses = static::hydrate($Verses);
        
        //var_dump($Verses);
        
        return $Verses;
    }
    
    protected static function _buildPassageQuery($Passages) {
        $query = array();
        
        foreach($Passages as $Passage) {
            foreach($Passage->chapter_verse_parsed as $parsed) {
                $q = '`book` = ' . $Passage->Book->id;
                
                // Single verses
                if($parsed['type'] == 'single') {
                    $q .= ' AND `chapter` = ' . $parsed['c'];
                    $q .= ($parsed['v']) ? ' AND `verse` = ' . $parsed['v'] : '';
                }
                elseif($parsed['type'] == 'range') {
                    if(!$parsed['cst'] && !$parsed['cen']) {
                        continue;
                    }
                    
                    $q .= ' AND (';
                    
                    // Intra-chapter ranges
                    if($parsed['cst'] == $parsed['cen']) {
                        $q .= '`chapter` =' . $parsed['cst'];
                        
                        if($parsed['vst'] && $parsed['ven']) {
                            $q .= ' AND `verse` BETWEEN ' . $parsed['vst'] . ' AND ' . $parsed['ven'];
                        }
                        else {
                            $q .= ($parsed['vst']) ? ' AND `verse` >= ' . $parsed['vst'] : '';
                            $q .= ($parsed['ven']) ? ' AND `verse` <= ' . $parsed['ven'] : '';
                        }
                    }
                    // Cross-chapter ranges
                    else {
                        $cvst = $parsed['cst'] * 1000 + intval($parsed['vst']);
                        $cven = $parsed['cen'] * 1000 + intval($parsed['ven']);
                        
                        if($parsed['vst'] && $parsed['ven']) {
                            $q .= '`chapter` * 1000 + `verse` BETWEEN ' . $cvst . ' AND ' . $cven;
                        }
                        else {
                            $q .= ($parsed['vst']) ? '     `chapter` * 1000 + `verse` >= ' . $parsed['vst'] : '     `chapter` >= ' . $parsed['cst'];
                            $q .= ($parsed['ven']) ? ' AND `chapter` * 1000 + `verse` <= ' . $parsed['ven'] : ' AND `chapter` <= ' . $parsed['cen'];
                        }
                    }
                    
                    $q .= ')';
                }
                
                $query[] = $q;
            }
        }
        
        return '(' . implode(') OR (', $query) . ')';
    }
    
    protected static function _buildSearchQuery($Search, $parameters) {
        return '1=1';
    }

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
