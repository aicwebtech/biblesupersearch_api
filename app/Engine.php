<?php

namespace App;

use App\User;
use App\Models\Bible;
use App\Passage;
use App\Search;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class Engine {
    use Traits\Error;
    use Traits\Input;

    protected $Bibles = array(); // Array of Bible objects
    protected $Bible_Primary = NULL; // Primary Bible version
    protected $languages = array();
    protected $default_data_format = 'passage';
    protected $default_page_all = FALSE;
    protected $metadata = NULL;
    public $debug = FALSE;

    public function __construct() {
        // Set the default Bible
        $default_bible = config('bss.defaults.bible');
        $this->addBible($default_bible);
        $this->setPrimaryBible($default_bible);
        $this->metadata = new \stdClass();
    }

    public function setBibles($modules) {
        $this->Bibles = array();
        $this->languages = array();

        if(is_string($modules)) {
            $decoded = json_decode($modules);
            $modules = (json_last_error() == JSON_ERROR_NONE) ? $decoded : $modules;
        }

        $modules = (is_array($modules)) ? $modules : array($modules);
        $Bibles = Bible::whereIn('module', $modules)->get();
        $primary = NULL;

        foreach($modules as $module) {
            if(empty($module)) {
                continue;
            }

            $added = $this->addBible($module);
            $primary = ($added && !$primary) ? $module : $primary;
        }

        $this->setPrimaryBible($primary);
    }

    public function setPrimaryBible($module) {
        if(!$module) {
            return FALSE;
        }

        if(!isset($this->Bibles[$module]) && !$this->addBible($module)) {
            return FALSE;
        }

        $this->Bible_Primary = $this->Bibles[$module];
        return TRUE;
    }

    public function addBible($module) {
        $Bible = Bible::findByModule($module);

        if($Bible) {
            $this->Bibles[$module] = $Bible;

            if(!in_array($Bible->lang_short, $this->languages)) {
                $this->languages[] = $Bible->lang_short;
            }
        }
        else {
            $this->addError( trans('errors.bible_no_exist', ['module' => $module]) );
            return FALSE;
        }

        return TRUE;
    }

    public function getBibles() {
        return $this->Bibles;
    }

    public function getMetadata($include_errors = FALSE) {
        if(!$include_errors) {
            return $this->metadata;
        }

        $metadata = $this->metadata;
        $metadata->errors = $this->getErrors();
        $metadata->error_level = $this->getErrorLevel();
        return $metadata;
    }

    protected function setMetadata($data) {
        $this->metadata = (object) $data;
    }

    /**
     * 'Query' API Action
     * Primary Bible Look Up And Search
     * Implements the bulk of the
     *
     * @param array $input request data
     * @return array $results search / look up results.
     */
    public function actionQuery($input) {
        // To do - add labels
        $parsing = array(
            'reference' => array(
                'type'  => 'string',
            ),
            'search' => array(
                'type'  => 'string',
            ),
            'request' => array(
                'type'  => 'string',
            ),
            'bible' => array(
                'type'  => 'array_string',
            ),
            'whole_words' => array(
                'type'  => 'bool',
                'default' => FALSE,
            ),
            'exact_case' => array(
                'type'  => 'bool',
                'default' => FALSE,
            ),
            'data_format' => array(
                'type'  => 'string',
                //'default' => 'passage', // breaking!
            ),
            'highlight' => array(
                'type'  => 'bool',
                'default' => FALSE,
            ),
            'page' => array(
                'type'  => 'int',
                'default' => 1,
            ),
            'page_all' => array(
                'type'  => 'bool',
                'default' => $this->default_page_all,
            ),
            'highlight_tag' => array(
                'type'  => 'string',
            ),
            'search_type' => array(
                'type'  => 'string',
            ),
            'proximity_limit' => array(
                'type'  => 'int',
            ),
            'callback' => array(
                'type'  => 'string',
            ),
            'search_all' => array(
                'type'  => 'string',
            ),
            'search_any' => array(
                'type'  => 'string',
            ),
            'search_one' => array(
                'type'  => 'string',
            ),
            'search_none' => array(
                'type'  => 'string',
            ),
            'search_phrase' => array(
                'type'  => 'string',
            ),
        );

        $this->resetErrors();
        $results = $bible_no_results = array();
        !empty($input['bible']) && $this->setBibles($input['bible']);
        $input = $this->_sanitizeInput($input, $parsing);
        $input['bible'] = array_keys($this->Bibles);
        $input['multi_bibles'] = (count($input['bible']) > 1) ? TRUE : FALSE;
        $input['data_format'] = (!empty($input['data_format'])) ? $input['data_format'] : $this->default_data_format;

        // Secondary search elements are detected automatically by Search class
        $references = empty($input['reference']) ? NULL : $input['reference'];
        $keywords   = empty($input['search'])    ? NULL : $input['search'];
        $request    = empty($input['request'])   ? NULL : $input['request'];

        if($references && $keywords && $request) {
            $this->addError(trans('errors.triple_request'), 4);
            return FALSE;
        }

        list($keywords, $references) = Passage::mapRequest($request, $keywords, $references, $this->languages, $this->Bibles);
        $Search     = Search::parseSearch($keywords, $input);
        $is_search  = ($Search) ? TRUE : FALSE;
        $paginate   = ($is_search && !$input['page_all'] && (!$input['multi_bibles'] || $this->_canPaginate($input['data_format']))) ? TRUE : FALSE;
        $paging     = array();

        if(!$is_search && empty($references)) {
            $this->addError(trans('errors.no_query'), 4);
            return FALSE;
        }

        // Passage parsing and validation
        $Passages = Passage::parseReferences($references, $this->languages, $is_search, $this->Bibles);

        if(is_array($Passages)) {
            foreach($Passages as $key => $Passage) {
                if($Passage->hasErrors()) {
                    $this->addErrors($Passage->getErrors(), $Passage->getErrorLevel());
                    unset($Passages[$key]);
                }
            }

            if(empty($Passages)) {
                $this->setErrorLevel(4);
                return FALSE; // If all of the passages are invalid, return
            }
        }

        if(!empty($references) && empty($Passages)) {
            $this->addError(trans('errors.passage_not_found', ['passage' => $references]), 4);
            return FALSE;
        }

        // Search validation
        if($Search) {
            $search_valid = $Search->validate();

            if(!$search_valid) {
                $this->addErrors($Search->getErrors(), $Search->getErrorLevel());
            }

            if($this->error_level == 4) {
                return FALSE;
            }
        }

        if(!$Search || $Search && $search_valid) {
            foreach($this->Bibles as $Bible) {
                $BibleResults = $Bible->getSearch($Passages, $Search, $input); // Laravel Collection

                if(!empty($BibleResults) && !$BibleResults->isEmpty()) {
                    $results[$Bible->module] = $BibleResults->all();

                    if($paginate && !$input['multi_bibles']) {
                        $paging = $this->_getCleanPagingData($BibleResults);
//                        var_dump(get_class($BibleResults));
//                        print_r($paging);
//                        die();
                    }

                    if($BibleResults->count() == config('bss.global_maximum_results')) {
                        $this->addError( trans('errors.result_limit_reached', ['maximum' => config('bss.global_maximum_results')]), 3);
                    }
                }
                else {
                    $bible_no_results[] = trans('errors.bible_no_results', ['module' => $Bible->module]);
                }
            }

            if(empty($results)) {
                if($Search) {
                    if($Search->hasErrors()) {
                        $this->addErrors($Search->getErrors(), $Search->getErrorLevel());
                    }
                    else {
                        $this->addError( trans('errors.no_results'), 4);
                    }
                }
                else {
                    $this->setErrorLevel(4);
                }
            }
            elseif(!empty($bible_no_results)) {
                $this->addErrors($bible_no_results, 3);
            }
        }

        if(is_array($Passages) && !$Search) {
            foreach($Passages as $Passage) {
                if($this->debug) {
                    print_r($Passage->chapter_verse_parsed);
                }

                if(!$Passage->claimVerses($results, TRUE)) {
                    $this->addError( trans('errors.passage_not_found', ['passage' => $Passage->raw_book . ' ' . $Passage->raw_chapter_verse]), 3);
                }
            }
        }

        $results = $this->_formatDataStructure($results, $input, $Passages, $Search);

        if($input['multi_bibles'] && $paginate) {
            $Paginator = $this->_buildPaginator($results, config('bss.pagination.limit'), $input['page']);
            $results = $Paginator->all();
            $paging = $this->_getCleanPagingData($Paginator);
        }
        elseif($input['multi_bibles'] && !$paginate) {
            $results = array_slice($results, 0, config('bss.global_maximum_results'));
        }

        $this->metadata->paging = $paging;
        return $results;
    }

    /**
     * API action query for getting a list of Bibles available to the user
     * @param array $input
     */
    public function actionBibles($input) {
        $include_desc = FALSE;
        $Bibles = Bible::select('name','shortname','module','year','lang','lang_short','copyright','italics','strongs','rank','research');
        $bibles = array(); // Array of associative arrays

        if($include_desc) {
            $Bibles -> addSelect('description');
        }

        if(array_key_exists('order_by_lang_name', $input) && !empty($input['order_by_lang_name'])) {
            $Bibles -> orderBy('lang', 'ASC') -> orderBy('name', 'ASC');
        }
        else {
            $Bibles -> orderBy('rank', 'ASC');
        }

        $Bibles = $Bibles -> where('enabled', 1) -> get() -> all();

        if(empty($Bibles)) {
            $this->addError(trans('errors.no_bible_enabled'));
            return FALSE;
        }

        foreach($Bibles as $Bible) {
            $bibles[$Bible->module] = $Bible->getAttributes();
        }

        return $bibles;
    }

    /**
     * API Action query for getting the list of books for the specified language.
     * @param array $input
     */
    public function actionBooks($input) {
        $language = (!empty($input['language'])) ? $input['language'] : env('DEFAULT_LANGUAGE_SHORT', 'en');
        $namespaced_class = 'App\Models\Books\\' . ucfirst($language);

        if(!class_exists($namespaced_class)) {
            $namespaced_class = 'App\Models\Books\\' . env('DEFAULT_LANGUAGE_SHORT', 'en');
        }

        $Books = $namespaced_class::select('id', 'name', 'shortname')->orderBy('id', 'ASC') -> get() -> all();
        return $Books;
    }

    public function actionStatics($input) {
        $response = new \stdClass;
        $response->bibles       = $this->actionBibles($input);
        $response->books        = $this->actionBooks($input);
        $response->shortcuts    = $this->actionShortcuts($input);
        $response->search_types = config('bss.search_types');
        $response->name         = config('app.name');
        $response->version      = config('app.version');
        $response->environment  = config('app.env');
        return $response;
    }

    public function actionShortcuts($input) {
        // Todo - multi language support
        $language = (!empty($input['language'])) ? $input['language'] : env('DEFAULT_LANGUAGE_SHORT', 'en');
        $namespaced_class = 'App\Models\Shortcuts\\' . ucfirst($language);

        if(!class_exists($namespaced_class)) {
            $namespaced_class = 'App\Models\Shortcuts\\' . env('DEFAULT_LANGUAGE_SHORT', 'en');
        }

        $Shortcuts = $namespaced_class::select('id', 'name', 'reference')->orderBy('id', 'ASC') ->where('display', 1) -> get() -> all();
        return $Shortcuts;
    }

    public function actionVersion($input) {
        $response = new \stdClass;
        $response->name         = config('app.name');
        $response->version      = config('app.version');
        $response->environment  = config('app.env');
        return $response;
    }

    protected function _formatDataStructure($results, $input, $Passages, $Search) {
        $format_type = (!empty($input['data_format'])) ? $input['data_format'] : $this->default_data_format;
        $parallel_unmatched_verses = TRUE;

        // Defines avaliable data formats and their aliases
        $format_map = array(
            'raw'       => 'minimal',
            'minimal'   => 'minimal',
            'passage'   => 'passage',
        );

        $format_type  = (array_key_exists($format_type, $format_map)) ? $format_map[$format_type] : 'passage';
        $format_class = '\App\Formatters\\' . ucfirst($format_type);

        // This doesn't work right!
        if($input['multi_bibles']) {
            if($parallel_unmatched_verses) {
                $results = $this->_parallelUnmatchedVerses($results, $Search);
            }

//            $limit = config('bss.pagination.limit');
//            $slice_len = ($input['page_all']) ? config('bss.global_maximum_results') : $limit;
//            $slice_off = ($input['page_all']) ? 0 : ($input['page'] - 1) * $limit;
//
//            foreach($results as &$res) {
//                $res = array_slice($res, $slice_off, $slice_len);
//            }
        }

        if($this->isTruthy('highlight', $input)) {
            $results = $this->_highlightResults($results, $Search);
        }

        $Formatter = new $format_class($results, $Passages, $Search);
        return $Formatter->format();
    }

    protected function _highlightResults($results, $Search) {
        if(!$Search) {
            return $results;
        }

        return $Search->highlightResults($results);
    }

    protected function _parallelUnmatchedVerses($results, $Search) {
        $bibles = $agg = array();
        $has_missing = FALSE;

        if(!$Search) {
            return $results;
        }

        foreach($results as $bible => $verses) {
            $bibles[] = $bible;

            foreach($verses as $key => $verse) {
                $bcv = $verse->book * 1000000 + $verse->chapter * 1000 + $verse->verse;

                if(!isset($agg[$bcv])) {
                    $agg[$bcv] = array();
                }

                $agg[$bcv][$bible] = $verse;
            }
        }

        $missing = $results_new = array_fill_keys($bibles, array());

        foreach($agg as $bcv => $verses) {
            foreach($bibles as $bible) {
                if(!array_key_exists($bible, $verses) || !is_object($verses[$bible])) {
                    $missing[$bible][] = $bcv;
                    $has_missing = TRUE;
                }
            }
        }

        if(!$has_missing) {
            return $results;
        }

        if($has_missing) {
            foreach($missing as $bible => $bcvs) {
                if(empty($bcvs)) {
                    continue;
                }

                $Bible = $this->Bibles[$bible];
                $found = $Bible->getVersesByBCV($bcvs);

                if(!is_array($found)) {
                    continue;
                }

                foreach($found as $verse) {
                    $bcv = $verse->book * 1000000 + $verse->chapter * 1000 + $verse->verse;
                    $agg[$bcv][$bible] = $verse;
                }
            }
        }

        ksort($agg, SORT_NUMERIC);

        foreach($agg as $bcv => $verses) {
            foreach($verses as $bible => $verse) {
                $results_new[$bible][] = $verse;
            }
        }

        return $results_new;
    }

    protected function _getCleanPagingData(\Illuminate\Pagination\LengthAwarePaginator $Paginator) {
        $paging = $Paginator->toArray();
        unset($paging['data']);
        unset($paging['next_page_url']);
        unset($paging['prev_page_url']);
        return $paging;
    }

    protected function _canPaginate($data_format) {
        return (strpos($data_format, 'passage') !== FALSE) ? TRUE : FALSE;
    }

    protected function _buildPaginator($data, $per_page, $current_page) {
        $total = count($data);
        $offset = $per_page * ($current_page - 1);
        $data = array_slice($data, $offset, $per_page);
        $Paginator = new Paginator($data, $total, $per_page, $current_page);
        return $Paginator;
    }

    protected function _sanitizeInput($input, $parsing) {
        $clean = array();

        foreach($parsing as $index => $s) {
            $value = NULL;

            if(array_key_exists($index, $input) && !empty($input[$index])) {
                switch($s['type']) {
                    case 'bool':
                        $value = ($input[$index]) ? TRUE : FALSE;
                        $value = (is_string($input[$index]) && ($input[$index] == 'false' || $input[$index] == 'off' || $input[$index] == 'no')) ? FALSE : $value;
                        break;
                    case 'array_string':
                    case 'string_array':
                        // This needs to be parsed here - just passing through now
                        $value = $input[$index];
                        break;
                    case 'int':
                        $value = intval($input[$index]);
                        break;
                    case 'string':
                        $value = strval($input[$index]);
                        break;
                    default:
                        $value = $input[$index];
                }
            }

            if(!$value && array_key_exists('default', $s)) {
                $clean[$index] = $s['default'];
            }
            elseif($value) {
                $clean[$index] = $value;
            }
        }

        return $clean;
    }

    public function setDefaultDataType($type) {
        $this->default_data_format = $type;
    }

    public function setDefaultPageAll($value) {
        $this->default_page_all = ($value) ? TRUE : FALSE;
    }
}

