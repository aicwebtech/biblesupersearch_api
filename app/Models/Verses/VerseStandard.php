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
        $is_special_search = ($Search && $Search->is_special);
        $Query = DB::table($table . ' AS tb');

        $reccommend_raw_query = FALSE;

        $Query->select('id','book','chapter','verse','text','italics');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');

        if($Passages) {
            $passage_query = static::_buildPassageQuery($Passages, NULL, $parameters);

            if($passage_query) {
                $Query->whereRaw('(' . $passage_query . ')');
            }
        }

        if($Search) {
            $reccommend_raw_query = $Search->isBooleanSearch();

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

        $binddata = !isset($binddata) ? [] : $binddata;

        try {
            if($Search && !$parameters['multi_bibles'] && !$parameters['page_all']) {
                $page_limit = min( (int) $parameters['page_limit'], (int) config('bss.global_maximum_results'));
                    
                if($reccommend_raw_query) {
                    $page = (!array_key_exists('page', $parameters) || !$parameters['page']) ? 1 : $parameters['page'];

                    // Manually query the count - we need this for the paginator
                    $sql_parts = explode('from', $Query->toSql());
                    $sql_count = 'SELECT COUNT(*) AS count FROM' . $sql_parts[1];
                    $count = (int) DB::select($sql_count, $binddata)[0]->count;
                    
                    $Query->limit($page_limit);
                    $Query->offset(($page - 1) * $page_limit);

                    $results = DB::select($Query->toSql(), $binddata);

                    // Manually drop the results into the paginator:
                    $verses = new \Illuminate\Pagination\LengthAwarePaginator($results, $count, $page_limit, $page);
                }
                else {
                    $verses = $Query->paginate($page_limit);
                }
            }
            else {
                $met = 120;
                $lim = config('bss.global_maximum_results');

                if($Search && $parameters['multi_bibles']) {
                    $met = 600;
                    $lim = config('bss.parallel_search_maximum_results');
                }

                ini_set('max_execution_time', $met);
                $lim && $Query->limit( $lim);

                if($reccommend_raw_query) {
                    $verses = collect( DB::select($Query->toSql(), $binddata) );
                }
                else {
                    $verses = $Query->get();
                }
            }
        }
        catch(\Exception $e) {
            $msg = $Query->toSql();
            $msg .= print_r($binddata, true);

            $msg .= $e->getMessage();

            throw new \Exception($msg);
        }


        if(config('app.debug_query')) {
            // $Query->dump();
            // $Query->dd();

            $_SESSION['debug']['query']      = $Query->toSql();
            $_SESSION['debug']['_raw_search_query']      = $search_query;
            $_SESSION['debug']['query_data'] = $Query->getBindings();
            $_SESSION['debug']['query_data_raw'] = (isset($binddata)) ? $binddata : NULL;
        }

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

                        $cvst = $parsed['cst'] * 1000 + (int) $parsed['vst'];
                        $cven = $parsed['cen'] * 1000 + (int) $parsed['ven'];
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

        $results_raw = DB::select($sql, $binddata);

        foreach($results_raw as $a1) {
            foreach($a1 as $val) {
                $results[] = (int) $val; // Flatten results into one-dimensional array
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
                $results[] = (int) $val; // Flatten results into one-dimensional array
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
        $in_console = (strpos(php_sapi_name(), 'cli') !== FALSE);

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

            $map['chapter_verse'] = (int) $map['chapter'] * 1000 + (int) $map['verse'];
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
            case 'book':
                $verse = static::select('book','chapter')->where('chapter', '=', 1)->where('verse', '=', 1)->inRandomOrder()->first();
                return array('book_id' => $verse->book, 'chapter_verse' => $verse->chapter);
                break;            

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
                $book = (int) ($single / 1000000);
                $chapter_verse = $single - $book * 1000000;
                $sub->where('book', $book);
                $sub->where('chapter_verse', $chapter_verse);
            });
        }

        return $Query->get()->all();
    }

    public function countVerses($book = null, $chapter = null, $verse = null)
    {
        $Query = DB::table($this->getTable() . ' AS tb');

        if($book) {
            $Query->where('book', $book);

        }

        if($chapter) {
            $Query->where('chapter', $chapter);
        }

        if($verse) {
            $range = explode('-', $verse);

            if(count($range) == 1) {
                $Query->where('verse', $verse);
            } else {
                $Query->whereBetween('verse', $range);
            }
        }

        return $Query->count();
    }

    public static function getStatistics($Passage, $input = [])
    {

        $b = $c = $v = null;

        $has_verses = $has_chapters = false;
        $table_fmt = ''; // for aliases

        $queries = [
            'passage' => [],
            'chapter' => [],
            'book' => [],
        ];

        $references = [

        ];

        $book_st = $book_en = $chapter = $chapter_verse = null;
        $chapters = [];

        if($Passage) {
            if(count($Passage->chapter_verse_normal)) {
                $chapter_verse = $Passage->chapter_verse;

                foreach($Passage->chapter_verse_normal as $parsed) {
                    $v_q = $c_q = $b_q = $table_fmt . '`book` = ' . $Passage->Book->id;
                    $book_st = $Passage->Book->id;

                    // Single verses
                    if($parsed['type'] == 'single') {
                        $c_q .= ' AND ' . $table_fmt . '`chapter` = ' . $parsed['c'];
                        $v_q .= ' AND ' . $table_fmt . '`chapter` = ' . $parsed['c'];
                        //                                                              $c_r .= ''
                        $v_q .= ($parsed['v']) ? ' AND ' . $table_fmt . '`verse` = ' . $parsed['v'] : '';

                        $has_verses = $parsed['v'] ? true : $has_verses;
                        $has_chapters = true;
                        $chapters[] = $parsed['c'];
                    }
                    elseif($parsed['type'] == 'range') {
                        if(!$parsed['cst'] && !$parsed['cen']) {
                            continue;
                        }

                        $has_verses = ($parsed['vst'] != 0 || $parsed['ven'] != 999) ? true : $has_verses; 
                        $has_chapters = true;

                        $cvst = $parsed['cst'] * 1000 + (int) $parsed['vst'];
                        $cven = $parsed['cen'] * 1000 + (int) $parsed['ven'];
                        $v_q .= ' AND ' . $table_fmt . '`chapter_verse` BETWEEN ' . $cvst . ' AND ' . $cven;
                        $c_q .= ' AND ' . $table_fmt . '`chapter` BETWEEN ' . $parsed['cst'] . ' AND ' . $parsed['cen'];

                        if($parsed['cst'] == $parsed['cen']) {
                            $chapters[] = $parsed['cst'];
                        } else {
                            $chapters[] = $parsed['cst'] . ' - ' . $parsed['cen'];
                        }
                        
                        // Proposed modification that would eliminate the need for the `chapter_verse` db column
                        //$q .= ' AND ' . $table_fmt . '`chapter` * 1000 + `verse` BETWEEN ' . $cvst . ' AND ' . $cven;
                    }

                    $queries['passage'][] = $v_q;
                    $queries['chapter'][] = $c_q;
                    $queries['book'][] = $b_q;
                }
            }
            else {
                if($Passage->is_book_range) {
                    $queries['book'][] = $table_fmt . '`book` BETWEEN ' . $Passage->Book->id . ' AND ' . $Passage->Book_En->id;
                    $book_st = $Passage->Book->id;
                    $book_en = $Passage->Book_En->id;
                }
                else {
                    $queries['book'][] = $table_fmt . '`book` = ' . $Passage->Book->id;
                    $book_st = $Passage->Book->id;
                }
            }
        }

        if($has_verses) {
            $queries['passage'] = '(' . implode(' OR ', $queries['passage']) . ')';

            $references['passage'] = [
                'book' => $book_st,
                'chapter_verse' => $chapter_verse,
            ];
        } else {
            unset($queries['passage']);
        }        

        if($has_chapters) {
            $queries['chapter'] = '('. implode(' OR ', $queries['chapter']) . ')';

            $references['chapter'] = [
                'book' => $book_st,
                'chapter_verse' => implode(', ', $chapters),
            ];
        } else {
            unset($queries['chapter']);
        }

        $queries['book'] = '(' . implode(' OR ', $queries['book']) . ')';
        
        $references['book'] = [
            'book_st' => $book_st,
            'book_en' => $book_en,
        ];

        $full_bible = self::statsHelper();

        $stats = [];

        foreach($queries as $type => $query) {
            $stats[$type] = self::statsHelper($type, $query, $references[$type], $full_bible);
        }

        unset($full_bible['_chapter_counts']);
        $stats['full'] = $full_bible;
        return $stats;
    }

    protected static function statsHelper($type = 'full', $query = null, $reference = null, $full_bible = [])
    {
        $sub = [
            'type'          => $type,
            'reference'     => $reference,
            'num_verses'    => null,
            'num_chapters'  => null,
            'num_books'     => null,
        ];

        $Verse = new static;
        $table = $Verse->getTable();
        $Query = DB::table($table . ' AS tb');

        $Query->select('id','book','chapter','verse','text','italics');
        $Query->orderBy('book', 'ASC')->orderBy('chapter', 'ASC')->orderBy('verse', 'ASC');

        if($query) {
            $Query->whereRaw('(' . $query . ')');
        }

        $Verses = $Query->get();
        $first = $Verses->first();

        $ChapterCounts = $Verses->countBy(function($verse) {
            return $verse->book . '_' . $verse->chapter;
        });       

        $sub['num_verses'] = $Verses->count();
        $sub['num_chapters'] = $ChapterCounts->count();

        $sub['num_books'] = $Verses->countBy(function($verse) {
            return $verse->book;
        })->count();

        switch($type) {
            case 'book':
                $sub['book_position'] = $sub['num_books'] == 1 ? $first->book : null;
                break;
            case 'chapter':  
                $FullChapterCounts = isset($full_bible['_chapter_counts']) ? $full_bible['_chapter_counts'] : null;
                
                if($sub['num_chapters'] == 1 && $FullChapterCounts) {
                    $cca_keys = array_keys($FullChapterCounts->toArray());
                    $chap_pos = array_search($first->book . '_' . $first->chapter, $cca_keys) + 1;
                    $sub['chapter_position'] = $chap_pos;
                } else {
                    $sub['chapter_position'] = null;
                }

                break;
            case 'passage':
                if($sub['num_verses'] == 1) {
                    // $ccidx = array_keys($ChapterCounts->all());
                    // $idx = array_search($first->book . '_' . $first->chapter, $ccidx);

                } else {

                }

                $sub['verse_position']  =  $sub['num_verses'] == 1 ? $first->id : null;
                break;
            case 'full':
                $sub['_chapter_counts'] = $ChapterCounts;
        }

        return $sub;
    }

    public function getChapterVerseCount($verbose = false) {
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
}
