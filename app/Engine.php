<?php

namespace App;

use App\User;
use App\Models\Bible;
use App\Models\Language;
use App\Passage;
use App\Search;
use App\CacheManager;
use App\Helpers;
use Illuminate\Support\Arr;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class Engine 
{

    use Traits\Error {
        resetErrors as traitResetErrors;
    }
    
    use Traits\Input;
    use Traits\Singleton;

    protected $Bibles = array(); // Array of Bible objects
    protected $Bible_Primary = NULL; // Primary Bible version
    protected $languages = array();
    protected $primary_language = NULL;
    protected $default_language = NULL;
    protected $default_data_format = 'passage';
    protected $default_page_all = FALSE;
    protected $metadata = NULL;
    protected $multi_bibles = FALSE;
    protected $chapter_verse_counts = NULL;
    public $debug = FALSE;
    public $allow_disabled_bibles = FALSE;

    public function __construct() 
    {
        // Set the default Bible
        $default_bible = config('bss.defaults.bible');
        $this->addBible($default_bible);
        $this->setPrimaryBible($default_bible);
        $this->metadata = new \stdClass();
    }

    public function setBibles($modules) 
    {
        $this->Bibles = array();
        $this->languages = array();
        $this->primary_language = NULL;
        $this->multi_bibles = FALSE;

        $default_language = $this->default_language ?: config('bss.defaults.language_short');

        $modules = $this->_parseInputArray($modules);
        $primary = NULL;

        foreach($modules as $module) {
            if(empty($module)) {
                continue;
            }

            $added = $this->addBible($module);
            $primary = ($added && !$primary) ? $module : $primary;
        }

        $this->setPrimaryBible($primary);

        if(!$this->primary_language) {
            $this->primary_language = $default_language;
            array_unshift($this->languages, $this->primary_language);
        }
    }

    protected function _parseInputArray($input) 
    {
        if(is_string($input)) {
            $decoded = json_decode($input);
            $input = (json_last_error() == JSON_ERROR_NONE) ? $decoded : $input;
        }

        $input = (is_array($input)) ? $input : array($input);
        return $input;
    }

    public function setPrimaryBible($module) 
    {
        if(!$module) {
            return FALSE;
        }

        if(!isset($this->Bibles[$module]) && !$this->addBible($module)) {
            return FALSE;
        }

        $this->Bible_Primary = $this->Bibles[$module];
        return TRUE;
    }

    public function setDefaultLanguage($lang) 
    {
        if($this->languageHasBookSupport($lang)) {
            $this->default_language = $lang;
            $this->languages[] = $lang;
            return TRUE;
        }

        return FALSE;
    }

    public function addBible($module) 
    {
        $Bible = Bible::findByModule($module);

        if($Bible && ($Bible->enabled || $this->allow_disabled_bibles)) {
            $this->Bibles[$module] = $Bible;

            if(!in_array($Bible->lang_short, $this->languages)) {
                $this->languages[] = $Bible->lang_short;
                
                if($Bible->lang_short == 'zh') {
                    $this->languages[] = 'zh_TW';
                    $this->languages[] = 'zh_CN';
                }
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

    public function getBibles() 
    {
        return $this->Bibles;
    }

    public function hasMultipleBibleLanguages()
    {
        $lang = null;

        foreach($this->Bibles as $Bible) {
            if($lang != $Bible->lang_short) {
                if(!$lang) {
                    $lang = $Bible->lang_short;
                } else {
                    return true;
                }
            }
        }

        return false;
    }

    public function getMetadata($include_errors = FALSE) 
    {
        if(!$include_errors) {
            return $this->metadata;
        }

        $metadata = $this->metadata;
        $metadata->errors = $this->getErrors();
        $metadata->error_level = $this->getErrorLevel();
        return $metadata;
    }

    protected function setMetadata($data) 
    {
        $this->metadata = (object) $data;
    }

    public function resetErrors() 
    {
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
    public function actionQuery($input) 
    {

        // To do - add labels
        $parsing = [
            'reference' => [
                'type' => 'string',
            ],
            'search' => [
                'type' => 'string',
            ],
            'request' => [
                'type' => 'string',
            ],
            'bible' => [
                'type' => 'array_string',
            ],
            'whole_words' => [
                'type' => 'bool',
                'default' => false,
            ],
            'whole_words_debug' => [  // temp, for attempting to debug slowness here!
                'type' => 'bool',
                'default' => false,
            ],
            'exact_case' => [
                'type' => 'bool',
                'default' => false,
            ],
            'results_list' => [
                'type' => 'bool',
                'default' => false,
            ],
            'results_list_cache_id' => [
                'type' => 'string',
                'default' => null,
            ],
            'data_format' => [
                'type' => 'string',
                //'default' => 'passage', // breaking!
            ],
            'highlight' => [
                'type' => 'bool',
                'default' => false,
            ],
            'page' => [
                'type' => 'int',
                'default' => 1,
            ],
            'page_all' => [
                'type' => 'bool',
                'default' => $this->default_page_all,
            ],
            'page_limit' => [
                'type' => 'int_pos',
                'default' => config('bss.pagination.limit'),
            ],
            'highlight_tag' => [
                'type' => 'string',
                //'default' => null,
            ],
            'search_type' => [
                'type' => 'string',
            ],
            'proximity_limit' => [
                'type' => 'int',
            ],
            'keyword_limit' => [
                'type' => 'int',
            ],
            'callback' => [
                'type' => 'string',
            ],
            'search_all' => [
                'type' => 'string',
            ],
            'search_any' => [
                'type' => 'string',
            ],
            'search_one' => [
                'type' => 'string',
            ],
            'search_none' => [
                'type' => 'string',
            ],
            'search_phrase' => [
                'type' => 'string',
            ],
            'context' => [
                'type' => 'bool',
                'default' => false,
            ],
            'group_passage_search_results' => [
                'type' => 'bool',
                'default' => false,
            ],
            'context_range' => [
                'type' => 'int',
                'default' => config('bss.context.range'),
            ],
            'language' => [
                'type' => 'string',
                'default' => null,
            ],
            'markup' => [
                'type' => 'string',
                'default' => 'none',
            ],
            'parallel_search_error_suppress' => [
                'type' => 'bool',
                'default' => false,
            ],
        ];

        $this->resetErrors();
        $results = $bible_no_results = array();
        
        $CacheManager = new CacheManager();
        $Cache = $CacheManager->createCache($input, $parsing); // bottleneck (confirmed on live API)
        
        $this->metadata = new \stdClass;
        $this->metadata->hash = $Cache->hash;
        $paging = $test = []; 
        
        $input = $this->_sanitizeInput($input, $parsing); // bottleneck?
        
        if($input['results_list_cache_id']) {
            if($input['results_list_cache_id'] == $this->metadata->hash) {
                $input['results_list'] = true;
            } else {
                $input['results_list'] = false;
                $ListCache = $CacheManager->getCacheByHash($input['results_list_cache_id']);
                $form_data = json_decode($ListCache->form_data, true);
                $form_data['results_list'] = true;

                $list_results = $this->actionQuery($form_data);
                $list_meta    = $this->metadata;

                // Reset some stuff (because of recursive actionQuery call)
                $this->resetErrors();
                $this->metadata = new \stdClass;
                $this->metadata->hash = $Cache->hash;
                $this->metadata->list = $list_meta->list;
                // $paging = $list_meta->paging; // Maybe not a good idea?
            }
        }

        $this->setDefaultLanguage($input['language']);
        !empty($input['bible']) && $this->setBibles($input['bible']);
        $multi_bible_languages = $this->hasMultipleBibleLanguages();

        $input['bible'] = array_keys($this->Bibles);
        $input['page_limit'] = min( (int) $input['page_limit'], (int) config('bss.global_maximum_results'));
        $parallel = $input['multi_bibles'] = (count($input['bible']) > 1);
        $input['data_format'] = (!empty($input['data_format'])) ? $input['data_format'] : $this->default_data_format;

        // Secondary search elements are detected automatically by Search class
        $references = empty($input['reference']) ? NULL : $input['reference'];
        $keywords   = empty($input['search'])    ? NULL : $input['search'];
        $request    = empty($input['request'])   ? NULL : $input['request'];

        if($references && $keywords && $request) {
            $this->addError(trans('errors.triple_request'), 4);
            return FALSE;
        }

        $this->checkSemiEmpty($references, 'reference');
        $this->checkSemiEmpty($keywords, 'search');
        $this->checkSemiEmpty($request, 'request');

        if($this->hasErrors()) {
            return false;
        }

        list($keywords, $references, $this->metadata->disambiguation, $disamb_book) = Passage::mapRequest($input, $this->languages, $this->Bibles);
        $input['search']    = $keywords ?: NULL;
        $input['reference'] = $references ?: NULL;
        unset($input['request']); 

        $Search     = Search::parseSearch($keywords, $input);
        $is_search  = ($Search) ? TRUE : FALSE;
        $paginate   = ($is_search && !$input['page_all'] && (!$input['multi_bibles'] || $this->_canPaginate($input['data_format'])));
        
        if(!$is_search && empty($references)) {
            $this->addError(trans('errors.no_query'), 4);
            return FALSE;
        }

        // Passage parsing and validation
        $Passages = Passage::parseReferences($references . ' ', $this->languages, $is_search, $this->Bibles, $input);

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
            if(empty($input['search_type'])) {
                $input['search_type'] = 'and';
            }

            $search_type = Arr::first(config('bss.search_types'), function($st) use ($input) {
                return $st['value'] == $input['search_type'] || 
                    isset($st['alias']) && is_array($st['alias']) && in_array($input['search_type'], $st['alias']);
            });

            if(!$search_type) {
                $this->addError(trans('errors.invalid_search.type_does_not_exist', ['type' => $input['search_type']]));
            }

            $multi_bible_languages_allow = config('bss.parallel_search_different_languages');

            if($multi_bible_languages) {
                if($multi_bible_languages_allow == 'never') {
                    $this->addError(trans('errors.invalid_search.multi_bible_languages'), 4);
                } else if ($multi_bible_languages_allow == 'search_type' && !$search_type['multi_lang']) {
                    $this->addError(trans('errors.invalid_search.multi_bible_languages_type'), 4);
                }
            }

            $search_valid = $Search->validate();

            if($search_valid) {
                $Search->sanitize();
                
                $strongs = Search::parseStrongs($keywords);

                if(!empty($strongs)) {
                    $Strongs = \App\Models\StrongsDefinition::whereIn('number', $strongs)
                        ->orderBy('number', 'asc')
                        ->get();

                    foreach($Strongs as $Str) {
                        $this->metadata->strongs[] = $this->_formatStrongs($Str->toArray());
                    }
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
            $has_search_results = FALSE;

            foreach($this->Bibles as $Bible) {
                try {
                    $BibleResults = $Bible->getSearch($Passages, $Search, $input); // Laravel Collection
                }
                catch (\Exception $e) {
                    $this->addTransError('errors.500', [], 4, 500);

                    if(config('app.debug')) {
                        $m = $e->getMessage();
                        $m = "<div style='width:800px;font-family:Monospace;white-space:pre-wrap;text- align:left'>" . $m . "</div>";
                        
                        $this->addError('DATABASE ERROR:');
                        $this->addError($m);
                    }

                    break;   
                }

                if(!empty($BibleResults) && !$BibleResults->isEmpty()) {
                    $results[$Bible->module] = $BibleResults->all();
                    $has_search_results = TRUE;
                    
                    $parallel_limit = config('bss.parallel_search_maximum_results');

                    if($paginate && !$input['multi_bibles'] && !$input['results_list']) {
                        $paging = $this->_getCleanPagingData($BibleResults);
                    }

                    if($parallel && $BibleResults->count() == $parallel_limit) {
                        $this->addError( trans('errors.result_limit_reached', ['maximum' => $parallel_limit]), 3);
                    }
                    else if($BibleResults->count() == config('bss.global_maximum_results')) {
                        $this->addError( trans('errors.result_limit_reached', ['maximum' => config('bss.global_maximum_results')]), 3);
                    }
                }
                else {
                    if($Search) {
                        $results[$Bible->module] = [];
                        $tr = ($parallel) ? 'errors.parallel_bible_no_results' : 'errors.bible_no_results';
                    } else {
                        $tr = 'errors.bible_no_results';
                    }

                    $tr = $input['parallel_search_error_suppress'] ? null : $tr;
                    
                    if($tr) {
                        $bible_no_results[] = trans($tr, ['module' => $Bible->name]);
                    }
                }

                unset($BibleResults);
            }

            if(!$has_search_results) {
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

        if(is_array($Passages) && (!$Search || $input['group_passage_search_results'])) {
            foreach($Passages as $Passage) {
                if($this->debug) {
                    print_r($Passage->chapter_verse_parsed);
                }

                if(!$Passage->claimVerses($results, TRUE)) {
                    $this->addError( trans('errors.passage_not_found', ['passage' => $Passage->raw_book . ' ' . $Passage->raw_chapter_verse]), 3);
                }
            }
        }

        if($input['results_list']) {
            $this->metadata->list = $Search ? $this->_formatResultsList($results, $input['page_limit'], $input['page']) : [];
        } 

        $results = $this->_formatDataStructure($results, $input, $Passages, $Search);

        if(($input['multi_bibles'] || $input['results_list']) && $paginate) {
            if($input['multi_bibles']) {
                $results = array_slice($results, 0, config('bss.parallel_search_maximum_results'));
            }

            $Paginator = $this->_buildPaginator($results, $input['page_limit'], $input['page']);
            $results = $Paginator->all();
            $paging = $this->_getCleanPagingData($Paginator);
        }
        elseif(($input['multi_bibles'] || $input['results_list']) && !$paginate) {
            $results = array_slice($results, 0, config('bss.global_maximum_results'));
        }

        $this->metadata->paging = $paging;

        return $results;
    }

    /**
     * API action query for getting a list of Bibles available to the user
     * @param array $input
     */
    public function actionBibles($input) 
    {
        $language_float = isset($input['language_float']) ? $input['language_float'] : null;

        $include_desc = FALSE;
        $Bibles = Bible::select('bibles.name','shortname','module','year','owner', 'description',
            'languages.name AS lang','lang_short','copyright','italics','strongs','red_letter',
            'paragraph','rank','research','bibles.restrict','copyright_id','copyright_statement', 
            'languages.rtl', 'languages.native_name AS lang_native');

        $Bibles->leftJoin('languages', 'bibles.lang_short', 'languages.code');
        $bibles = array(); // Array of associative arrays

        $order_by_default = 'lang_native_name|rank';
        $order_by = array_key_exists('bible_order_by', $input) ? $input['bible_order_by'] : $order_by_default;
        $group_by = array_key_exists('bible_group_by', $input) ? $input['bible_group_by'] : NULL;
        $group_by_mapped = NULL;

        // if($group_by && $group_by != 'none') {
        //     switch($group_by) {
        //         case 'language':
        //             $group_by_mapped = 'lang_name';
        //             break;            
        //         case 'language_english':
        //             $group_by_mapped = 'lang_name_english';
        //             break;
        //         default:
        //             $group_by_mapped = NULL;
        //     }

        //     $order_by = ($group_by_mapped ?: $group_by) . '|' . $order_by;
        // }

        if($include_desc) {
            $Bibles -> addSelect('description');
        }

        // Legacy order by flag - still supported for now
        if(array_key_exists('order_by_lang_name', $input) && !empty($input['order_by_lang_name'])) {
            $Bibles -> orderBy('lang', 'ASC') -> orderBy('name', 'ASC');
        }
        else {
            foreach(explode('|', $order_by) as $ob) {
                switch($ob) {
                    case 'lang_name':
                        $Bibles -> orderBy('lang_native', 'ASC');      
                        break;

                    case 'lang_name_english':
                        $Bibles -> orderBy('lang', 'ASC');
                        break;         

                    case 'rank':
                    case 'name':
                    case 'shortname':
                        $language_float = null; // language float ignored in these cases
                    case 'lang_short':
                        $Bibles -> orderBy($ob, 'ASC');
                        break; 
                }
            }
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

        if($language_float) {
            $bibles_floated = [];

            foreach($bibles as $key => $bible) {
                if($bible['lang_short'] == $language_float) {
                    $bibles_floated[$key] = $bible;
                    unset($bibles[$key]);
                }
            }

            foreach($bibles as $key => $bible) {
                $bibles_floated[$key] = $bible;
            }
        }

        return $bibles;
    }

    /**
     * API action for rendering of a Bible
     * @param array $input
     */
    public function actionRender($input) 
    {    
        return $this->_renderDownloadHelper($input, 'render');
    }
    
    /**
     * API action for checking if rendering is needed of a Bible
     * @param array $input
     */
    public function actionRenderNeeded($input) 
    {    
        return $this->_renderDownloadHelper($input, 'render_needed');
    }

    /**
     * API action for downloading a rendering of a Bible
     * This action, when successful, returns a file, and not a standard JSON output
     * @param array $input
     */
    public function actionDownload($input) 
    {
        return $this->_renderDownloadHelper($input, 'download');
    }

    protected function _renderDownloadHelper($input, $action = 'render') 
    {
        $download = ($action == 'download');

        if(empty($input['bible'])) {
            $this->addError('Bible is required', 4);
        }

        if(empty($input['format'])) {
            $this->addError('Format is required', 4);
        }

        if($this->hasErrors()) {
            return FALSE;
        }

        RenderManager::cleanUpTempZipFiles();

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
            $zip = (bool) ($input['zip']);
        }
        else {
            $zip = FALSE;
        }

        $bypass_limit = (array_key_exists('bypass_limit', $input) && $input['bypass_limit']);

        $sanitized = [
            'format'    => $format,
            'modules'   => $modules,
            'zip'       => $zip,
            'email'     => array_key_exists('email', $input) ? $input['email'] : NULL,
            'contents'  => array_key_exists('contents', $input) ? $input['contents'] : NULL,
        ];

        $response = new \stdClass;
        $Manager = new RenderManager($modules, $format, $zip);

        if($Manager->hasErrors()) {
            $this->addErrors( $Manager->getErrors(), $Manager->getErrorLevel());
            $response->success = FALSE;
            $response->separate_process_supported = $Manager->separateProcessSupported();
            return $response;
        }

        if($action == 'render_needed') {
            $bibles_needing_render = $Manager->getBiblesNeedingRender(NULL, FALSE, FALSE, 0);
            $success = !($bibles_needing_render === FALSE || count($bibles_needing_render) > 0);
            $Manager->cleanUpTempFiles();
            $response->render_needed = !($success);
            $response->bibles_needing_render = [];

            if(is_array($bibles_needing_render)) {
                foreach($bibles_needing_render as $Bible) {
                    $response->bibles_needing_render[] = $Bible->module;
                }
            }

            if(!$success) {
                $this->addError('Render needed', 1);
            }
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

    protected function _startQueueProcess($queue = 'default') 
    {
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

    public function actionDownloadlist($input) 
    {
        return RenderManager::getRendererList();
    }

    /**
     * API Action query for getting the list of books for the specified language.
     * @param array $input
     */
    public function actionBooks($input) 
    {
        $language = (!empty($input['language'])) ? $input['language'] : config('bss.defaults.language_short');

        if($language == 'ALL') {
            $list = \App\Models\Language::haveBookSupport();
            $books_by_lang = [];

            foreach($list as $lang) {
                $namespaced_class = \App\Models\Books\BookAbstract::getClassNameByLanguageStrict($lang);

                // $namespaced_class = 'App\Models\Books\\' . ucfirst($lang);
                $books_by_lang[$lang] = $namespaced_class::select('id', 'name', 'shortname')->orderBy('id', 'ASC') -> get() -> all();
            }

            return $books_by_lang;
        } else if(strpos($language, '_') !== false) {
            // Todo - add locale support.
            list($language, $locale) = explode('_', $language);
        }

        $namespaced_class = \App\Models\Books\BookAbstract::getClassNameByLanguage($language);

        // $namespaced_class = 'App\Models\Books\\' . ucfirst($language);

        // if(!class_exists($namespaced_class)) {
        //     $namespaced_class = 'App\Models\Books\\' . ucfirst( config('bss.defaults.language_short') );
        // }

        $Books = $namespaced_class::select('id', 'name', 'shortname')->orderBy('id', 'ASC') -> get() -> all();
        $cvc = $this->getChapterVerseCounts(false);

        $books = [];

        foreach($Books as $Book) {
            $attr = $Book->getAttributes();

            if($Book->id > 66) {
                continue; // Apoc books not currently supported; will cause breakage because we don't have chapter/verse counts for them
            }

            $attr['chapters']       = $cvc[$Book->id]['chapters'];
            $attr['chapter_verses'] = $cvc[$Book->id]['chapter_verses'];
            $books[] = $attr;
        }

        return $books;
    }

    public function getChapterVerseCounts($return_diffs = false) 
    {
        // We use the KJV as the baseline, however if it's not enabled, we use the default Bible
        $Bible = Bible::findByModule('kjv'); 

        if(!$Bible || !$Bible->enabled) {
            $Bible = Bible::findByModule(config('bss.defaults.bible'));
        }

        if(!isset($this->chapter_verse_counts)) {
            $this->chapter_verse_counts = $Bible->getChapterVerseCount();
        }

        if(!$return_diffs) {
            return $this->chapter_verse_counts;
        }

        $Bibles = Bible::where('enabled', 1)->get();
        $diffs = [];

        foreach($Bibles as $B) {
            if($B->module == $Bible->module) {
                continue;
            }

            $b_same = true;
            $b_cvc = [];
            $cvc_diff = $B->getChapterVerseCount();

            foreach($cvc_diff as $book_id => $book) {
                if($book['chapters'] != $this->chapter_verse_counts[$book_id]['chapters']) {
                    $b_same = false;
                    $b_cvc[$book_id]['chapters'] = $book['chapters'];
                }
                
                foreach($book['chapter_verses'] as $chapter => $verses) {
                    if(!isset($this->chapter_verse_counts[$book_id]['chapter_verses'][$chapter])) {
                        //$b_same = false;
                        //$b_cvc[$book_id]['chapter_verses'][$chapter] = $verses;
                        //echo('Missing chapter ' . $chapter . ' in ' . $book_id . 'in ' . $B->module);
                        continue;
                    }
                    
                    if($verses != $this->chapter_verse_counts[$book_id]['chapter_verses'][$chapter]) {
                        $b_same = false;
                        $b_cvc[$book_id]['chapter_verses'][$chapter] = $verses;
                    }
                }
            }

            if(!$b_same) {
                $diffs[$B->module] = $b_cvc;
            }
        }

        return $diffs;
    }

    public function languageHasBookSupport($lang) 
    {
        return Language::hasBookSupport($lang);
    }

    /**
     * returns data that the API needs to run
     */
    public function actionStatics($input) 
    {
        $domain = isset($input['domain']) ? $input['domain'] : null;
        $Access = ApiAccessManager::lookUpByInput($input);

        RenderManager::cleanUpTempZipFiles();

        $response = new \stdClass;
        $response->bibles                   = $this->actionBibles($input);
        $response->books                    = $this->actionBooks($input);
        //$response->books_count_diffs        = $this->getChapterVerseCounts(true);
        $response->shortcuts                = $this->actionShortcuts($input);
        $response->download_enabled         = (bool) config('download.enable');
        $response->download_limit           = config('download.enable') ? config('download.bible_limit') : FALSE;
        $response->download_formats         = $response->download_enabled ? array_values(RenderManager::getGroupedRendererList()) : [];
        $response->search_types             = config('bss.search_types');
        $response->name                     = config('app.name');
        $response->hash                     = $this->_getNameHash();
        $response->version                  = config('app.version');
        $response->api_version              = config('app.api_version');
        $response->api_version_list         = config('app.api_version_list');
        $response->environment              = config('app.env');
        $response->research_desc            = config('bss.research_description');
        $response->parallel_lang_search     = config('bss.parallel_search_different_languages');
        $response->access                   = new \stdClass;
        $response->access->allowed          = !$Access->isAccessRevoked();
        $response->access->limit            = $Access->getAccessLimit();
        $response->access->limit_reached    = $Access->isLimitReached();
        $response->access->hits             = $Access->getDailyHits();
        return $response;
    }

    /**
     * returns statistics about the indicated passage(s)
     */
    public function actionStatistics($input) 
    {
        $parsing = [
            'reference' => [
                'type' => 'string',
            ],
            'bible' => [
                'type' => 'array_string'
            ],
            'language' => [
                'type' => 'string',
                'default' => NULL,
            ],
        ];

        $this->resetErrors();
        $results = $bible_no_results = [];

        $input = $this->_sanitizeInput($input, $parsing);
        $this->setDefaultLanguage($input['language']);
        !empty($input['bible']) && $this->setBibles($input['bible']);

        $input['bible'] = array_keys($this->Bibles);
        $parallel = $input['multi_bibles'] = (count($input['bible']) > 1);

        $references = empty($input['reference']) ? NULL : $input['reference'];
        $is_search = false;

        if(empty($references)) {
            $this->addError(trans('errors.no_query'));
        }

        $this->checkSemiEmpty($references, 'reference');

        if($this->hasErrors()) {
            return false;
        }

        Passage::$allow_book_range_without_search = true;

        // Passage parsing and validation
        $Passages = Passage::parseReferences($references . ' ', $this->languages, $is_search, $this->Bibles, $input);
        
        Passage::$allow_book_range_without_search = false;

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


        foreach($this->Bibles as $Bible) {
            try {
                $stats = $Bible->getStatistics($Passages[0]);
                $results[$Bible->module] = $stats;
            }
            catch (\Exception $e) {
                $this->addTransError('errors.500', [], 4, 500);
                throw $e;

                // if(config('app.debug')) {
                //     $m = $e->getMessage();
                //     $m = "<div style='width:800px;font-family:Monospace;white-space:pre-wrap;text- align:left'>" . $m . "</div>";
                    
                //     $this->addError('DATABASE ERROR:');
                //     $this->addError($m);
                // }

                break;   
            }
        }


        return $results;
    }

    private function _getNameHash() 
    {
        return hash('sha256', config('app.name_static'));
    }

    public function actionStaticsChanged($input) 
    {
        $response = new \stdClass;
        $response->success = TRUE;
        $response->dates = new \stdClass;

        $response->dates->bible     = strtotime(Bible::max('updated_at'));                  // Most recent Bible change
        $response->dates->shortcuts = strtotime(Models\Shortcuts\En::max('updated_at'));    // Most recent shortcut change
        $response->dates->configs   = (int) config('app.configs_updated_at');               // most recent config change, including most recent update

        $response->updated = max((array) $response->dates);
        // $response->updated_dt = date('Y-m-d H:i:s', $response->updated);

        return $response;
    }

    public function actionShortcuts($input) 
    {
        // Todo - multi language support
        $language = (!empty($input['language'])) ? $input['language'] : config('bss.defaults.language_short');
        $namespaced_class = 'App\Models\Shortcuts\\' . ucfirst($language);

        if(!class_exists($namespaced_class)) {
            $namespaced_class = 'App\Models\Shortcuts\\' . ucfirst( config('bss.defaults.language_short') );
        }

        $Shortcuts = $namespaced_class::select('id', 'name', 'short1', 'short2', 'reference', 'display')
            ->orderBy('id', 'ASC') 
            ->where('display', 1) // remove in v 5.5 RE BSS-57
            -> get() -> all();
        
        return $Shortcuts;
    }

    public function actionVersion($input) 
    {
        $response = new \stdClass;
        $response->name             = config('app.name');
        $response->hash             = $this->_getNameHash();
        $response->version          = config('app.version');
        $response->api_version      = config('app.api_version');
        $response->api_version_list = config('app.api_version_list');
        $response->environment      = config('app.env');

        // pher - unpublished property 'php version' checks against current required PHP version
        if(array_key_exists('pher', $input) && $input['pher']) {
            $composer_txt = file_get_contents(base_path() . '/composer.json');
            $composer     = json_decode($composer_txt);

            $php_version = substr($composer->require->php, 2);
            $php_success = (version_compare($input['pher'], $php_version, '>=') == -1);

            $response->php_required_min = $php_success ? NULL : $php_version;
            $response->php_error = !$php_success;
        }

        return $response;
    }

    public function actionStrongs($input) 
    {
        $response = [];
        
        if(!array_key_exists('strongs', $input) || empty($input['strongs'])) {
            return $this->addError( __('errors.strongs_input_required'), 4);
        }

        $strongs = strip_tags(trim($input['strongs']));

        if(preg_match_all('/[GHgh][0-9]+/', $strongs, $matches)) {
            foreach($matches[0] as $raw) {
                // Remove padding zeros
                $t = $raw[0];
                $n = (string)(int) substr($raw, 1);
                $clean = $t . $n;

                $Def = \App\Models\StrongsDefinition::where('number', $clean)->first();

                if(!$Def) {
                    $this->addError( __('errors.strongs_not_found') . ': ' . $clean);
                }
                else {
                    $response[] = $this->_formatStrongs($Def->toArray());
                }
            }
        }

        return $response;
    }

    protected function _formatStrongs($attr) 
    {
        $attr['tvm'] = $attr['tvm'] ? preg_replace('/<b>Count:<\/b> [0-9]+.*?<br>/', '', $attr['tvm']) : null; // Remove 'count' from TVM
        unset($attr['created_at']);
        unset($attr['updated_at']);
        return $attr;
    }

    public function actionReadcache($input) 
    {
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

    public function actionRequirements($input)
    {
        return InstallManager::getChecklist();
    }

    protected function _formatDataStructure($results, $input, $Passages, $Search) 
    {
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
            $results = $this->_highlightResults($results, $Search, $Passages, $input);
        }

        $Formatter = new $format_class($results, $Passages, $Search, $this->languages, $input);
        return $Formatter->format();
    }

    protected function _formatResultsList($results, $limit, $page)
    {
        $formatted = [];

        foreach($results as $b => $vs) {
            foreach($vs as $v) {
                $idx  = str_pad($v->book, 2, '0', STR_PAD_LEFT);
                $idx .= '_' . str_pad($v->chapter, 3, '0', STR_PAD_LEFT);
                $idx .= '_' . str_pad($v->verse, 3, '0', STR_PAD_LEFT);

                if(isset($formatted[$idx])) {
                    continue;
                }

                $f = new \stdClass;
                $f->book = $v->book;
                $f->chapter = $v->chapter;
                $f->verse = $v->verse;
                $f->showing = false;

                $formatted[$idx] = $f;
            }
        }

        ksort($formatted);
        $formatted = array_values($formatted);

        $st_idx = $page * $limit - $limit; // ($page - 1) * $limit
        $en_idx = $st_idx + $limit - 1;

        for($i = $st_idx; $i <= $en_idx; $i ++) {
            if(isset($formatted[$i])) {
                $formatted[$i]->showing = true;
            }
        }

        return $formatted;
    }

    protected function _highlightResults($results, $Search, $Passages, $input) 
    {
        $highlight_tag = array_key_exists('highlight_tag', $input) ? $input['highlight_tag'] : config('bss.defaults.highlight_tag');;

        if($Search) {
            $results = $Search->highlightResults($results, $highlight_tag);
        }

        if($Passages && count($Passages) == 1 && $input['context']) {
            $results = $Passages[0]->highlightContext($results, $highlight_tag);
        }

        return $results;
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

    protected function _parallelUnmatchedVerses($results, $Search) 
    {
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
                    $verse->_unmatched = true; // Note: these 'unmatched' verses are never highlighted
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

    protected function _getCleanPagingData(\Illuminate\Pagination\LengthAwarePaginator $Paginator) 
    {
        $paging = $Paginator->toArray();
        unset($paging['data']);
        unset($paging['next_page_url']);
        unset($paging['prev_page_url']);
        return $paging;
    }

    protected function _canPaginate($data_format) 
    {
        $data_format = strtolower($data_format);
        $allowed     = ['passage', 'lite'];
        return in_array($data_format, $allowed);
    }

    protected function _buildPaginator($data, $per_page, $current_page) 
    {
        $total = count($data);
        $offset = $per_page * ($current_page - 1);
        $data = array_slice($data, $offset, $per_page);
        $Paginator = new Paginator($data, $total, $per_page, $current_page);
        return $Paginator;
    }

    protected function _sanitizeInput($input, $parsing) 
    {
        $clean = array();

        foreach($parsing as $index => $s) {
            $value = NULL;

            if(array_key_exists($index, $input) && !empty($input[$index])) {
                switch($s['type']) {
                    case 'bool':
                        $value = (bool) $input[$index];
                        $value = (is_string($input[$index]) && ($input[$index] == 'false' || $input[$index] == 'off' || $input[$index] == 'no')) ? FALSE : $value;
                        break;
                    case 'array_string':
                    case 'string_array':
                        // This needs to be parsed here - just passing through now
                        $value = $input[$index];
                        break;
                    case 'int':
                        $value = (int) $input[$index];
                        break;                    
                    case 'int_pos':
                        $value = (int) $input[$index];
                        $value = $value < 0 ? NULL : $value;
                        break;
                    case 'string':
                        $value = (string) $input[$index];
                        $value = str_replace("\n", ' ', $value);
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

    public function setDefaultDataType($type) 
    {
        $this->default_data_format = $type;
    }

    public function setDefaultPageAll($value) 
    {
        $this->default_page_all = (bool) $value;
    }

    // Detect and error for cases when there is an input, but it's effectively empty
    protected function checkSemiEmpty($value, $as = 'request') 
    {
        if(empty($value) || !is_string($value)) {
            // If it's actually empty, no error. Re: we have separate checks for this
            return true; 
        }
        
        $value_org = $value;
        $value = trim($value);
        $value = str_replace('_', ' ', $value);

        if(empty($value)) {
            // If it's actually empty, no error. Re: we have separate checks for this
            return true; 
        }

        if($as == 'search' || $as == 'request') {
            if(preg_match("/\p{L}/", $value)) {
                return true;
            }            
        }

        if($as == 'reference' || $as == 'request') {
            if(preg_match("/[\p{L}\d]/", $value)) {
                return true;
            }            
        }

        if($as == 'reference') {
            $tstr = 'errors.passage_not_found';
            $prop = ['passage' => $value_org];
        } else {
            $tstr = 'errors.invalid_search.general';
            $prop = ['search' => $value_org, 'loc' => 'EN 1'];
        }

        return $this->addTransError($tstr, $prop, 4);
    }

    public static function getHardcodedVersion() 
    {
        $app_configs = include(base_path('config/app.php'));
        return $app_configs['version'];
    }
    
    /**
     * Get's the version number of the production version of this API
     * Used for checking for updates
     * @return type
     */
    public static function getUpstreamVersion($verbose = FALSE) 
    {
        if(!config('app.phone_home')) {
            return FALSE;
        }

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

    public static function triggerInvalidConfigError() 
    {
        
    }

    public static function isBibleEnabled($module) 
    {
        $Bible = Bible::findByModule($module);
        return($Bible && $Bible->installed && $Bible->enabled);
    }
}

