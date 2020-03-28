<?php

namespace App;

use App\User;
use App\Models\Bible;
use App\Passage;
use App\Search;
use App\CacheManager;
use App\Helpers;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class Engine {
    use Traits\Error {
        resetErrors as traitResetErrors;
    }
    
    use Traits\Input;
    use Traits\Singleton;

    protected $Bibles = array(); // Array of Bible objects
    protected $Bible_Primary = NULL; // Primary Bible version
    protected $languages = array();
    protected $primary_language = NULL;
    protected $default_data_format = 'passage';
    protected $default_page_all = FALSE;
    protected $metadata = NULL;
    protected $multi_bibles = FALSE;
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
        $this->primary_language = NULL;
        $this->multi_bibles = FALSE;

        $modules = $this->_parseInputArray($modules);
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
        $this->primary_language = $this->primary_language ?: config('bss.defaults.language_short');
    }

    protected function _parseInputArray($input) {
        if(is_string($input)) {
            $decoded = json_decode($input);
            $input = (json_last_error() == JSON_ERROR_NONE) ? $decoded : $input;
        }

        $input = (is_array($input)) ? $input : array($input);
        return $input;
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

        if($Bible && $Bible->enabled) {
            $this->Bibles[$module] = $Bible;

            if(!in_array($Bible->lang_short, $this->languages)) {
                $this->languages[] = $Bible->lang_short;
            }

            if(!$this->primary_language && $this->languageHasBookSupport($Bible->lang_short)) {
                $this->primary_language = $Bible->lang_short;
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

    public function resetErrors() {
        $this->traitResetErrors();
        $this->metadata = new \stdClass;
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
            'whole_words_debug' => array(  // temp, for attempting to debug slowness here!
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
            'keyword_limit' => array(
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
            'context' => array(
                'type'   => 'bool',
                'default' => FALSE,
            ),
            'context_range' => array(
                'type'   => 'int',
                'default' => config('bss.context.range'),
            ),
            'markup' => array(
                'type'  => 'string',
                'default' => 'none'
            ),
        );

        $this->resetErrors();
        $results = $bible_no_results = array();

        $CacheManager = new CacheManager();
        $Cache = $CacheManager->createCache($input, $parsing);
        $this->metadata = new \stdClass;
        $this->metadata->hash = $Cache->hash;

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

        list($keywords, $references, $this->metadata->disambiguation, $disamb_book) = Passage::mapRequest($input, $this->languages, $this->Bibles);
        $Search     = Search::parseSearch($keywords, $input);
        $is_search  = ($Search) ? TRUE : FALSE;
        $paginate   = ($is_search && !$input['page_all'] && (!$input['multi_bibles'] || $this->_canPaginate($input['data_format']))) ? TRUE : FALSE;
        $paging     = array();

        if(!$is_search && empty($references)) {
            $this->addError(trans('errors.no_query'), 4);
            return FALSE;
        }

        // Passage parsing and validation
        $Passages = Passage::parseReferences($references, $this->languages, $is_search, $this->Bibles, $input);

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

        $this->metadata->strongs = [];

        // Search validation
        if($Search) {
            $search_valid = $Search->validate();
            $strongs = Search::parseStrongs($keywords);

            if(!empty($strongs)) {
                $Strongs = \App\Models\StrongsDefinition::whereIn('number', $strongs)
                    ->orderBy('number', 'asc')
                    ->get();

                foreach($Strongs as $Str) {
                    $this->metadata->strongs[] = $this->_formatStrongs($Str->toArray());
                }
            }

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
                    }

                    if($BibleResults->count() == config('bss.global_maximum_results')) {
                        $this->addError( trans('errors.result_limit_reached', ['maximum' => config('bss.global_maximum_results')]), 3);
                    }
                }
                else {
                    $bible_no_results[] = trans('errors.bible_no_results', ['module' => $Bible->module]);
                }

                unset($BibleResults);
            }

            if(empty($results)) {
                if($Search) {
                    if($Search->hasErrors()) {
                        $this->addErrors($Search->getErrors(), $Search->getErrorLevel());
                    }
                    else {
                        // No results error message
                        // If the request found a 'disambiguation' book, send that instead of error messge
                        if($disamb_book) {
                            unset($input['request'], $input['search'], $input['reference']);

                            foreach($this->metadata->disambiguation as $disambig) {
                                if($disambig['type'] == 'book') {
                                    $input['reference'] = $disambig['data']['reference'];
                                    return $this->actionQuery($input);
                                    break;
                                }
                            }
                        }

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
        $Bibles = Bible::select('bibles.name','shortname','module','year','languages.name AS lang','lang_short','copyright','italics','strongs','red_letter',
                'paragraph','rank','research','copyright_id','copyright_statement');

        $Bibles->leftJoin('languages', 'bibles.lang_short', 'languages.code');
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

        $Bibles = $Bibles -> where('enabled', 1) -> with('copyrightInfo') -> get() -> all();

        if(empty($Bibles)) {
            $this->addError(trans('errors.no_bible_enabled'));
            return FALSE;
        }

        foreach($Bibles as $Bible) {
            $bibles[$Bible->module] = $Bible->getAttributes();
            $bibles[$Bible->module]['downloadable'] = $Bible->isDownloadable();
            $bibles[$Bible->module]['copyright_statement'] = $Bible->getCopyrightStatement();
        }

        return $bibles;
    }

    /**
     * API action for rendering of a Bible
     * @param array $input
     */
    public function actionRender($input) {    
        return $this->_renderDownloadHelper($input, 'render');
    }
    
    /**
     * API action for checking if rendering is needed of a Bible
     * @param array $input
     */
    public function actionRenderNeeded($input) {    
        return $this->_renderDownloadHelper($input, 'render_needed');
    }

    /**
     * API action for downloading a rendering of a Bible
     * This action, when successful, returns a file, and not a standard JSON output
     * @param array $input
     */
    public function actionDownload($input) {
        return $this->_renderDownloadHelper($input, 'download');
    }

    protected function _renderDownloadHelper($input, $action = 'render') {
        $download = ($action == 'download') ? TRUE : FALSE;

        if(empty($input['bible'])) {
            $this->addError('Bible is required', 4);
        }

        if(empty($input['format'])) {
            $this->addError('Format is required', 4);
        }

        if($this->hasErrors()) {
            return FALSE;
        }

        if($input['bible'] == 'ALL') {
            $modules = 'ALL';
        }
        else {
            $this->setBibles($input['bible']);
            $modules = array_keys($this->Bibles);
        }

        if($input['format'] == 'ALL') {
            $format = 'ALL';
        }
        else {
            $format = $this->_parseInputArray($input['format']);
        }

        if(array_key_exists('zip', $input)) {
            $zip = ($input['zip']) ? TRUE : FALSE;
        }
        else {
            $zip = FALSE;
        }

        $bypass_limit = (array_key_exists('bypass_limit', $input) && $input['bypass_limit']) ? TRUE : FALSE;

        $sanitized = [
            'format'    => $format,
            'modules'   => $modules,
            'zip'       => $zip,
            'email'     => array_key_exists('email', $input) ? $input['email'] : NULL,
            'contents'  => array_key_exists('contents', $input) ? $input['contents'] : NULL,
        ];

        $Manager = new \App\RenderManager($modules, $format, $zip);

        if($action == 'render_needed') {
            $bibles_needing_render = $Manager->getBiblesNeedingRender(NULL, FALSE, FALSE, 0);
            $success = ($bibles_needing_render === FALSE || count($bibles_needing_render) > 0) ? FALSE : TRUE;
            $Manager->cleanUpTempFiles();
        }
        else {
            // if($bypass_limit) {
            //     $success = $Manager->render(FALSE, TRUE, TRUE);
            //     $success = ($download) ? $Manager->download() : $success;
            // }
            // else {
                $success = ($download) ? $Manager->download($bypass_limit) : $Manager->render(FALSE, TRUE, $bypass_limit);
                // $success = ($download) ? $Manager->download() :  $Manager->getBiblesNeedingRender();
            // }

        }

        $response = new \stdClass;

        if(!$success) {
            // if($Manager->needsProcess()) {
            //     $HasJobs = Models\Job::where('queue', 'default')->count();

            //     var_dump($HasJobs);

            //     \App\Jobs\ProcessRender::dispatch($sanitized);

            //     if(!$HasJobs) {
            //         // $this->_startQueueProcess();
            //     }
            // }

            $this->addErrors( $Manager->getErrors(), $Manager->getErrorLevel());
            $response->success = FALSE;
            $response->separate_process_supported = $Manager->separateProcessSupported();
            // return FALSE;
        }
        elseif(!$download) {
            $response->success = TRUE;
        }
        
        return $response;
    }

    protected function _startQueueProcess($queue = 'default') {
        $cmd = 'php ' . $_SERVER['DOCUMENT_ROOT'] . '../artisan queue:work --stop-when-empty'; 

        // $cmd .= ' > /dev/null 2>&1';
        // $cmd .= ' > /dev/null & ';
        $cmd .= ' > /dev/null ';

        // Use Laravel queues???

        // See these options on php artisan queue:work
        //  --once
        //  --stop-when-empty

        var_dump($cmd);
        // die($cmd);

        exec($cmd);
        return TRUE;
    }

    public function actionDownloadlist($input) {
        return \App\RenderManager::getRendererList();
    }

    /**
     * API Action query for getting the list of books for the specified language.
     * @param array $input
     */
    public function actionBooks($input) {
        $language = (!empty($input['language'])) ? $input['language'] : config('bss.defaults.language_short');

        if($language == 'ALL') {
            $list = \App\Models\Books\BookAbstract::getSupportedLanguages();
            $books_by_lang = [];

            foreach($list as $lang) {
                $namespaced_class = 'App\Models\Books\\' . ucfirst($lang);
                $books_by_lang[$lang] = $namespaced_class::select('id', 'name', 'shortname')->orderBy('id', 'ASC') -> get() -> all();
            }

            return $books_by_lang;
        }

        $namespaced_class = 'App\Models\Books\\' . ucfirst($language);

        if(!class_exists($namespaced_class)) {
            $namespaced_class = 'App\Models\Books\\' . config('bss.defaults.language_short');
        }

        $Books = $namespaced_class::select('id', 'name', 'shortname')->orderBy('id', 'ASC') -> get() -> all();
        return $Books;
    }

    public function languageHasBookSupport($lang) {
        return in_array($lang, \App\Models\Books\BookAbstract::getSupportedLanguages());
    }

    public function actionStatics($input) {
        $response = new \stdClass;
        $response->bibles           = $this->actionBibles($input);
        $response->books            = $this->actionBooks($input);
        $response->shortcuts        = $this->actionShortcuts($input);
        $response->download_enabled = config('download.enable') ? TRUE : FALSE;
        $response->download_formats = $response->download_enabled ? array_values(RenderManager::getGroupedRendererList()) : [];
        $response->search_types     = config('bss.search_types');
        $response->name             = config('app.name');
        $response->version          = config('app.version');
        $response->environment      = config('app.env');
        return $response;
    }

    public function actionShortcuts($input) {
        // Todo - multi language support
        $language = (!empty($input['language'])) ? $input['language'] : config('bss.defaults.language_short');
        $namespaced_class = 'App\Models\Shortcuts\\' . ucfirst($language);

        if(!class_exists($namespaced_class)) {
            $namespaced_class = 'App\Models\Shortcuts\\' . config('bss.defaults.language_short');
        }

        $Shortcuts = $namespaced_class::select('id', 'name', 'reference')->orderBy('id', 'ASC') ->where('display', 1) -> get() -> all();
        return $Shortcuts;
    }

    public function actionVersion($input) {
        $response = new \stdClass;
        $response->name         = config('app.name');
        $response->version      = config('app.version');
        $response->environment  = config('app.env');

        // pher - unpublished property 'php version' checks against current required PHP version
        if(array_key_exists('pher', $input) && $input['pher']) {
            $composer_txt = file_get_contents(base_path() . '/composer.json');
            $composer     = json_decode($composer_txt);

            $php_version = substr($composer->require->php, 2);
            $php_success = (version_compare($input['pher'], $php_version, '>=') == -1) ? TRUE : FALSE;
            // var_dump($php_version);
            // var_dump($input['pher']);

            $response->php_required_min = $php_success ? NULL : $php_version;
            $response->php_error = !$php_success;
        }

        return $response;
    }

    public function actionStrongs($input) {
        $response = [];
        $strongs = strip_tags(trim($input['strongs']));

        if(preg_match_all('/[GHgh][0-9]+/', $strongs, $matches)) {
            foreach($matches[0] as $clean) {
                $Def = \App\Models\StrongsDefinition::where('number', $clean)->first();

                if(!$Def) {
                    $this->addError('Strong\s Number ' . $clean . ' not found');
                }
                else {
                    $response[] = $this->_formatStrongs($Def->toArray());
                }
            }
        }

        return $response;
    }

    protected function _formatStrongs($attr) {
        $attr['tvm'] = preg_replace('/<b>Count:<\/b> [0-9]+.*?<br>/', '', $attr['tvm']); // Remove 'count' from TVM
        unset($attr['created_at']);
        unset($attr['updated_at']);
        return $attr;
    }

    public function actionReadcache($input) {
        if(!array_key_exists('hash', $input)) {
            $this->addError('hash is required', 4);
            return;
        }

        $Cache = \App\Models\Cache::where('hash', $input['hash'])->first();

        if(!$Cache) {
            $this->addError('Cache not found', 4);
        }
        else {
            $cache = $Cache->toArray();
            $cache['form_data'] = json_decode($cache['form_data'], TRUE);
            return $cache;
        }
    }

    protected function _formatDataStructure($results, $input, $Passages, $Search) {
        $format_type = (!empty($input['data_format'])) ? $input['data_format'] : $this->default_data_format;
        $parallel_unmatched_verses = TRUE;

        // Defines avaliable data formats and their aliases
        $format_map = array(
            'raw'       => 'minimal',
            'minimal'   => 'minimal',
            'passage'   => 'passage',
            'lite'      => 'lite',
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

        $results = $this->_processMarkup($results, $input['markup']);

        if($this->isTruthy('highlight', $input)) {
            $results = $this->_highlightResults($results, $Search);
        }

        $Formatter = new $format_class($results, $Passages, $Search, $this->languages);
        return $Formatter->format();
    }

    protected function _highlightResults($results, $Search) {
        if(!$Search) {
            return $results;
        }

        return $Search->highlightResults($results);
    }

    protected function _processMarkup($results, $mode) {
        if($mode == 'raw') {
            return $results;
        }

        $find = ['‹','›', '[', ']', '} {'];
        $pattern = '/\{[^\}]+}/';

        foreach($results as $bible => &$bible_results) {
            foreach($bible_results as &$verse) {
                $verse->text = str_replace($find, '', $verse->text);
                $verse->text = preg_replace($pattern, '', $verse->text);
            }
            unset($verse);
        }
        unset($bible_results);

        return $results;
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
        $data_format = strtolower($data_format);
        $allowed     = ['passage', 'lite'];
        return (in_array($data_format, $allowed)) ? TRUE : FALSE;
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

    public static function getHardcodedVersion() {
        $app_configs = include(base_path('config/app.php'));
        return $app_configs['version'];
    }
    
    /**
     * Get's the version number of the production version of this API
     * Used for checking for updates
     * @return type
     */
    public static function getUpstreamVersion($verbose = FALSE) {
        $json = NULL;
        $url  = 'https://api.biblesupersearch.com/api/version';

        if($verbose) {
            $ver = explode('.', PHP_VERSION);
            $php_version = (int) $ver[0] . '.' . (int) $ver[1] . '.' . (int) $ver[2];
            $url .= '?pher='. $php_version;
        }

        if(ini_get('allow_url_fopen') == 1) {
            $json = file_get_contents($url);
        }

        // Attempt 2: Fall back to cURL
        if(!$json === FALSE && function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);
            // curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            $json = curl_exec($ch);
            curl_close($ch);
        }

        if(!$json) {
            return NULL;
        }

        $results = json_decode($json);

        if($verbose) {
            $results->results->local_php_version = $php_version;
        }

        return $verbose ? $results->results : $results->results->version;
    }

    public static function isBibleEnabled($module) {
        $Bible = Bible::findByModule($module);
        return($Bible && $Bible->installed && $Bible->enabled) ? TRUE : FALSE;
    }
}

