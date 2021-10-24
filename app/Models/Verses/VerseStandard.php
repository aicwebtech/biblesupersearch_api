<?php

namespace App\Models\Verses;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Bible;
use App\Passage;
use App\Search;
use DB;

/**
 * Class VerseStandard
 *
 * Base model class for all Bibles stored on local DB using standard format
 */

class VerseStandard extends VerseAbstract {
    protected static $special_table = 'bible';

    /**
     * Processes and executes the Bible search query
     *
     * @param array $Passages Array of App/Passage instances, represents the passages requested, if any
     * @param App/Search $Search App/Search instance, representing the search keywords, if any
     * @param array $parameters Search parameters - user input
     * @return array $Verses array of Verses instances (found verses)
     */
    public static function getSearch($Passages = NULL, $Search = NULL, $parameters = array()) {
        $Verse = new static;
        $table = $Verse->getTable();
        $passage_query = $search_query = NULL;
        $is_special_search = ($Search && $Search->is_special) ? TRUE : FALSE;
        $Query = DB::table($table . ' AS tb');
        
        // $Query->select(DB::raw('SQL_NO_CACHE id, book, chapter, verse, text'));

        $Query->select('id','book','chapter','verse','text','italics');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');

        if($Passages) {
            $passage_query = static::_buildPassageQuery($Passages, NULL, $parameters);

            if($passage_query) {
                $Query->whereRaw('(' . $passage_query . ')');
            }
        }

        if($Search) {
            if($is_special_search) {
                $table = static::$special_table . '_1';
                $passage_query_special = static::_buildPassageQuery($Passages, $table, $parameters);
                $search_query = static::_buildSpecialSearchQuery($Search, $parameters, $passage_query_special);

                if(!$search_query) {
                    return FALSE; // No results
                }

                $Query->whereRaw('(' . $search_query . ')');
            }
            else {
                list($search_query, $binddata) = static::_buildSearchQuery($Search, $parameters);

                if(!$search_query) {
                    return FALSE;
                }

                if(!config('app.query_use_named_placeholders')) {
                    $binddata = array_values($binddata);
                }

                $Query->whereRaw('(' . $search_query . ')', $binddata);
            }
        }

        // echo(PHP_EOL . $Query->toSql() . PHP_EOL);
        // var_dump($binddata);
        // die();

        // $verses = DB::select($Query->toSql(), $binddata);
        // print_r($verses);

        if($Search && !$parameters['multi_bibles'] && !$parameters['page_all']) {
            $page_limit = min( (int) $parameters['page_limit'], (int) config('bss.global_maximum_results'));
            $verses = $Query->paginate($page_limit);
        }
        else {
            ini_set('max_execution_time', 120);
            $Query->limit( config('bss.global_maximum_results') );
            $verses = $Query->get();
        }

        if(config('app.debug_query')) {
            $_SESSION['debug']['query']      = $Query->toSql();
            $_SESSION['debug']['query_data'] = (isset($binddata)) ? $binddata : NULL;
            // $_SESSION['debug']['query_raw_output'] = $verses->all();
        }

        // $verses = $Query->get();
        // print_r($verses->all());
        // die();

        return (empty($verses)) ? FALSE : $verses;
    }

    protected static function _buildPassageQuery($Passages, $table = '', $parameters = array()) {
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
                        
                        // Proposed modification that would eliminate the need for the `chapter_verse` db column
                        //$q .= ' AND ' . $table_fmt . '`chapter` * 1000 + `verse` BETWEEN ' . $cvst . ' AND ' . $cven;
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

        if(config('app.debug')) {
            $_SESSION['debug']['prox_query']      = $sql;
            $_SESSION['debug']['prox_query_data'] = (isset($binddata)) ? $binddata : NULL;
        }

        // print($sql);
        // print_r($binddata);
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
        $results = [];

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

            $ps_chapter = ' AND (' . $alias . '.book != 19 OR '  . $alias . '.chapter = ' . $alias2 . '.chapter )'; // Always limit within chapter for Psalms
            $join .= (strpos($operator, '~l') === 0) ? ' AND ' . $alias . '.chapter = ' . $alias2 . '.chapter' : $ps_chapter; // Limit within chapter
            $join .= ' AND ' . $alias . '.id BETWEEN ' . $alias2 . '.id - ' . $limit . ' AND ' . $alias2 . '.id + ' . $limit;
        }

        $join .= ' AND ' . $on_clause;
        return $join;
    }

    // Todo - prevent installation if already installed!
    public function install($structure_only = FALSE) {
        $in_console = (strpos(php_sapi_name(), 'cli') !== FALSE) ? TRUE : FALSE;

        if (Schema::hasTable($this->table)) {
            return TRUE;
        }

        Schema::create($this->table, function (Blueprint $table) {
            //$table->charset('utf8mb4');
            //$table->collate('utf8mb4_unicode_ci');

            //$table->increments('id');
            $table->integer('id', TRUE);
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
            $table->index(['book', 'chapter_verse'], 'ixcv');
            $table->index(['book', 'chapter', 'verse'], 'ixbcv'); // Composite index on b, c, v
            //$table->index('text'); // Needs length - not supported in Laravel?
        });

        if($structure_only) {
            return TRUE;
        }

        $Zip = $this->Bible->openModuleFile();

        if(!$Zip) {
            return FALSE;
        }

        $info   = $Zip->getFromName('info.json');
        $verses = $Zip->getFromName('verses.txt');
        $Zip->close();

        $info   = json_decode($info, TRUE);
        $del    = ($info['delimiter']) ? $info['delimiter'] : '|';
        $fields = ($info['fields']) ? $info['fields'] : ["book","chapter","verse","text","italics","strongs"];
        $verses = preg_split("/\\r\\n|\\r|\\n/", $verses);
        $table = $this->getTable();
        $insertable = array();
        $ins_count = 0;

        foreach($verses as $verse) {
            if(empty($verse) || $verse[0] == '#') {
                continue;
            }

            $verse = explode($del, $verse);
            $map = array();

            foreach($fields as $index => $field) {
                $map[$field] = $verse[$index];
            }

            $map['chapter_verse'] = $map['chapter'] * 1000 + $map['verse'];
            $insertable[] = $map;
            $ins_count ++;

            // Chunk size of 100 has proven to be the most efficient
            // if(count($insertable) >= 100) {
            if($ins_count >= 100) {
                DB::table($table)->insert($insertable);
                $insertable = [];
                $ins_count = 0;
            }
        }

        DB::table($table)->insert($insertable); // Finish inserting data
        return TRUE;
    }

    public function uninstall() {
        if (Schema::hasTable($this->table)) {
            Schema::drop($this->table);
        }

        return TRUE;
    }

    public function exportData() {
        $data = array();

        $closure = function($rows) use (&$data) {
           foreach($rows as $row) {
               $data[] = $row;
           }
        };

        self::orderBy('id')->chunk(100, $closure);
        return $data;
    }

    public function getRandomReference($random_mode) {
        switch($random_mode) {
            case 'chapter':
                $verse = static::select('book','chapter')->where('verse', '=', 1)->inRandomOrder()->first();
                return array('book_id' => $verse->book, 'chapter_verse' => $verse->chapter);
                break;

            case 'verse':
                $verse = static::select('book','chapter','verse')->inRandomOrder()->first();
                return array('book_id' => $verse->book, 'chapter_verse' => $verse->chapter . ':' . $verse->verse);
                break;

            default:
                return FALSE;
        }
    }

    /**
     * Fetches verses by BCV
     * $bcv = $book * 1000000 + $chapter * 1000 + $verse
     * @param array|int $bcv
     * @return array $Verses array of Verses instances (found verses)
     */
    public function getVersesByBCV($bcv) {
        $bcv = (is_array($bcv)) ? $bcv : array($bcv);
        $Query = DB::table($this->getTable() . ' AS tb')->select('id','book','chapter','verse','text');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');

        foreach($bcv as $single) {
            $Query->orwhere(function($sub) use ($single) {
                $book = intval($single / 1000000);
                $chapter_verse = $single - $book * 1000000;
                $sub->where('book', $book);
                $sub->where('chapter_verse', $chapter_verse);
            });
        }

        return $Query->get()->all();
    }

    public function getChapterVerseCount($verbose = FALSE) {
        $counts = [];

        // We use MAX instead of COUNT because of missing verses in Critical Text 'Bibles' that will throw off the numbers
        $chapters = static::select('book', DB::raw('MAX(chapter) AS chapters'))->groupBy('book')->get();
        $verses   = static::select('book', 'chapter', DB::raw('MAX(verse) AS verses'))->groupBy('book', 'chapter')->get();

        foreach($chapters as $c) {
            $counts[$c->book] = [
                'chapters_max'   => $c->chapters,
                'chapters'       => $c->chapters,
                'chapter_verses' => [],
            ];
        }

        foreach($verses as $v) {
            if($verbose) {
                $counts[$v->book]['chapter_verses'][$v->chapter] = [
                    'verses'     => $v->verses,
                    'verses_max' => $v->verses,
                ];
            }
            else {
                $counts[$v->book]['chapter_verses'][$v->chapter] = $v->verses;
            }
        }

        return $counts;
    }

    /*
     * KEEP THIS, FOR NOW
     * protected static function _buildPassageQuery__OLD($Passages) {
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
