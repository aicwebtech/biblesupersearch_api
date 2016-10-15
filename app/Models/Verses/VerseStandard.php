<?php

namespace App\Models\Verses;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Bible;
use App\Passage;
use App\Search;
use DB;

class VerseStandard extends VerseAbstract {
    protected static $special_table = 'bible';
    
    /**  
     * Processes and executes the Bible search query
     * 
     * @param array $Passages Array of App/Passage instances, represents the passages requested, if any
     * @param App/Search $Search App/Search instance, reporesenting the search keywords, if any
     * @param array $parameters Search parameters - user input
     * @return array $Verses array of Verses instances (found verses)
     */
    public static function getSearch($Passages = NULL, $Search = NULL, $parameters = array()) {
        $Verse = new static;
        $table = $Verse->getTable();
        $passage_query = $search_query = NULL;
        $is_special_search = ($Search && $Search->is_special) ? TRUE : FALSE;
        $Query = DB::table($table . ' AS tb')->select('id','book','chapter','verse','text');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');
        
        if($Passages) {
            $passage_query = static::_buildPassageQuery($Passages);
            
            if($passage_query) {
                $Query->whereRaw('(' . $passage_query . ')');
            }
        }
        
        if($Search) {
            if($is_special_search) {
                $table = static::$special_table . '_1';
                $passage_query_special = static::_buildPassageQuery($Passages, $table);
                $search_query = static::_buildSpecialSearchQuery($Search, $parameters, $passage_query_special);
                
                if(!$search_query) {
                    return array(); // No results
                }
                
                $Query->whereRaw('(' . $search_query . ')');
            }
            else {                
                list($search_query, $binddata) = static::_buildSearchQuery($Search, $parameters);
                
                if(!config('app.query_use_named_placeholders')) {
                    $binddata = array_values($binddata);
                }
                
                $Query->whereRaw('(' . $search_query . ')', $binddata);
            }
        }
        
        //echo(PHP_EOL . $Query->toSql() . PHP_EOL);
        //var_dump($binddata);
        $verses = $Query->get();
        return (empty($verses)) ? FALSE : $verses;
    }
    
    protected static function _buildPassageQuery($Passages, $table = '') {
        if(empty($Passages)) {
            return FALSE;
        }
        
        $query = array();
        $table_fmt = ($table) ? '`' . $table . '`.' : '';
        
        foreach($Passages as $Passage) {
            if(count($Passage->chapter_verse_normal)) {                
                foreach($Passage->chapter_verse_normal as $parsed) {
                    $q = $table_fmt . '`book` = ' . $Passage->Book->id;

                    // Single verses
                    if($parsed['type'] == 'single') {
                        $q .= ' AND ' . $table_fmt . '`chapter` = ' . $parsed['c'];
                        $q .= ($parsed['v']) ? ' AND ' . $table_fmt . '`verse` = ' . $parsed['v'] : '';
                    }
                    elseif($parsed['type'] == 'range') {
                        if(!$parsed['cst'] && !$parsed['cen']) {
                            continue;
                        }

                        $cvst = $parsed['cst'] * 1000 + intval($parsed['vst']);
                        $cven = $parsed['cen'] * 1000 + intval($parsed['ven']);
                        $q .= ' AND ' . $table_fmt . '`chapter_verse` BETWEEN ' . $cvst . ' AND ' . $cven;
                    }

                    $query[] = $q;
                }
            }
            else {
                if($Passage->is_book_range) {
                    $query[] = $table_fmt . '`book` BETWEEN ' . $Passage->Book->id . ' AND ' . $Passage->Book_En->id;
                }
                else {
                    $query[] = $table_fmt . '`book` = ' . $Passage->Book->id;
                }
            }
        }
        
        return '(' . implode(') OR (', $query) . ')';
    }
    
    protected static function _buildSearchQuery($Search, $parameters) {
        if(empty($Search)) {
            return '';
        }
        
        $Search->setUseNamedBindings(config('app.query_use_named_placeholders'));
        return $Search->generateQuery();
    }
    
    protected static function _buildSpecialSearchQuery($Search, $parameters, $lookup_query = NULL) {
        $joins = $binddata = $selects = $results = array();
        $Verse = new static;
        $table = DB::getTablePrefix() . $Verse->getTable();
        $alias = static::$special_table;
        $prev_full_alias = $alias . '_1'; // Cache for the last table alias referenced
        $from = ' FROM ' . $table . ' AS ' . $prev_full_alias;
        $selects[] = $prev_full_alias . '.id AS id_1';
        
        list($Searches, $operators) = $Search->parseProximitySearch();
        
        $SubSearch1 = array_shift($Searches);
        list($where, $binddata) = $SubSearch1->generateQuery($binddata, $alias . '_1');
        $where = ($lookup_query) ? '(' . $lookup_query . ') AND (' . $where . ')' : $where;
        
        foreach($Searches as $key => $SubSearch) {
            $in = $key + 2;
            $full_alias = $alias . '_' . $in;
            $selects[] = $full_alias . '.id AS id_' . $in;
            list($on_clause, $binddata)  = $SubSearch->generateQuery($binddata, $full_alias);
            $joins[] = static::_buildSpecialSearchJoin($table, $full_alias, $operators[$key], $prev_full_alias, $parameters, $on_clause);
            $prev_full_alias = $full_alias;
        }
        
        // Need to use raw queries because of complex JOIN statements
        $sql = 'SELECT ' . implode(', ', $selects) . PHP_EOL . $from . PHP_EOL . implode(PHP_EOL, $joins) . PHP_EOL . 'WHERE ' . $where;
        
        //echo PHP_EOL;
        //var_dump($sql);
        //var_dump($binddata);
        //die();

        $results_raw = DB::select($sql, $binddata);

        foreach($results_raw as $a1) {
            foreach($a1 as $val) {
                $results[] = intval($val); // Flatten results into one-dimensional array
            }
        }
        
        $results = array_unique($results);
        return (empty($results)) ? FALSE : '`id` IN (' . implode(',', $results) . ')';
    }
    
    static function proximityQueryTest($query) {
        $results_raw = DB::select($query);

        foreach($results_raw as $a1) {
            foreach($a1 as $val) {
                $results[] = intval($val); // Flatten results into one-dimensional array
            }
        }
        
        $results = array_unique($results);
        return count($results);
    }
    
    protected static function _buildSpecialSearchJoin($table, $alias, $operator, $alias2, $parameters, $on_clause) {
        $join  = 'INNER JOIN ' . $table . ' AS ' . $alias . ' ON ';
        $join .= $alias . '.book = ' . $alias2 . '.book';
        $operator = trim($operator);
        
        if($operator == '~b' ) {
            // Do nothing more - book join is always included
        }
        elseif($operator == '~c') {
            $join .= ' AND ' . $alias . '.chapter = ' . $alias2 . '.chapter';
        }
        else {
            $lppos = strpos($operator, '(');
            
            if($lppos !== FALSE) {
                $limit = intval(substr($operator, $lppos + 1));
            }
            else {
                $limit = (empty($parameters['proximity_limit'])) ? 5 : $parameters['proximity_limit'];
            }
            
            $join .= (strpos($operator, '~l') === 0) ? ' AND ' . $alias . '.chapter = ' . $alias2 . '.chapter' : ''; // Limit within chapter
            $join .= ' AND ' . $alias . '.id BETWEEN ' . $alias2 . '.id - ' . $limit . ' AND ' . $alias2 . '.id + ' . $limit;
        }
        
        $join .= ' AND ' . $on_clause;
        return $join;
    }

    // Todo - prevent installation if already installed!
    public function install() {
        if (Schema::hasTable($this->table)) {
            return TRUE;
        }

        $in_console = (strpos(php_sapi_name(), 'cli') !== FALSE) ? TRUE : FALSE;

        Schema::create($this->table, function (Blueprint $table) {
            //$table->charset('utf8mb4');
            //$table->collate('utf8mb4_unicode_ci'); 
            
            $table->increments('id');
            $table->tinyInteger('book')->unsigned();
            $table->tinyInteger('chapter')->unsigned();
            $table->tinyInteger('verse')->unsigned();
            $table->mediumInteger('chapter_verse')->unsigned();
            $table->text('text')->charset('utf8');
            $table->text('italics')->nullable();
            $table->text('strongs')->nullable();
            $table->index('book', 'ixb');
            $table->index('chapter', 'ixc');
            $table->index('verse', 'ixv');
            $table->index('chapter_verse', 'ixcv');
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
            
            DB::statement('SET NAMES utf8;');
            DB::statement('SET CHARACTER SET utf8');
            
            // we use this to determine what the strongs / italics fileds are
            $v_test = DB::select("SELECT * FROM {$v2_table} ORDER BY `index` LIMIT 1");
            $strongs = $italics = 'NULL';

            $strongs = isset($v_test[0]->strongs) ? 'strongs' : $strongs;
            $italics = isset($v_test[0]->italics) ? 'italics' : $italics;
            $italics = isset($v_test[0]->map) ? 'map' : $italics;

            
            $prefix = DB::getTablePrefix();
            $table = $this->getTable();
            
            $sql = "
                INSERT INTO {$prefix}{$table} (id, book, chapter, verse, chapter_verse, text, italics, strongs)
                SELECT `index`, book, chapter, verse, chapter * 1000 + verse, text, {$italics}, {$strongs}
                FROM {$v2_table}
            ";
            
            DB::insert($sql);
            
            /*
            $inc = 1000;
            $sql_ins = "INSERT INTO {$prefix}{$table} VALUES (:index, :book, :chapter, :verse, :chapter_verse, :text, :italics, :strongs)";
            
            for($lim = 0; $lim <= 40000; $lim += $inc) {
                $sql_sel = " 
                    SELECT `index`, book, chapter, verse, text, {$italics} AS italics, {$strongs} AS strongs
                    FROM {$v2_table}
                    LIMIT {$lim}, {$inc}
                ";
                    
                $verses = DB::select($sql_sel);
                
                foreach($verses as $verse) {
                    $binddata = array(
                        ':index'            => $verse->index,
                        ':book'             => $verse->book,
                        ':chapter'          => $verse->chapter,
                        ':chapter_verse'    => $verse->chapter * 1000 + $verse->verse,
                        ':verse'            => $verse->verse,
                        ':text'             => trim($verse->text),
                        ':italics'          => $verse->italics,
                        ':strongs'          => $verse->strongs,
                    );
                    
                    DB::insert($sql_ins, $binddata);
                }
            }
             * 
             */
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
    
    /*
    protected static function _buildPassageQuery__OLD($Passages) {
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
                            $q .= ($parsed['vst']) ? '     `chapter` * 1000 + `verse` >= ' . $cvst : '     `chapter` >= ' . $parsed['cst'];
                            $q .= ($parsed['ven']) ? ' AND `chapter` * 1000 + `verse` <= ' . $cven : ' AND `chapter` <= ' . $parsed['cen'];
                        }
                    }
                    
                    $q .= ')';
                }
                
                $query[] = $q;
            }
        }
        
        return '(' . implode(') OR (', $query) . ')';
    }
     * 
     * 
     */
}
