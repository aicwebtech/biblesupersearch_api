<?php

namespace App;

/**
 * Class for searching SQL database given a string of keywords
 */
class SqlSearch {
    use Traits\Error;
    use Traits\Input;

    protected $search; // String containing the search keywords
    protected $search_parsed;
    protected $terms; // Search keys for current search
    protected $options = [];
    protected $languages = [];
    protected $search_type = 'and';

    protected $options_default = array(
        'search_type'   => 'and',
        'whole_words'   => FALSE,
        'whole_words_debug'   => FALSE,
        'exact_case'    => FALSE,
        'keyword_limit' => 2,
    );

    //protected $use_unnamed_bindings = FALSE;
    protected $use_named_bindings = FALSE;
    public $search_fields = 'text'; // Comma separated

    // Base regexp for matching all valid Unicode characters in search terms
    // Needs to be used in all search processing regexp
    // Todo - including ALL punctuation fixes one problem, but causes other breakage
    // Need to figure out exactly what punctuation needs to be included here

    // Currently included:
    // \p{L}: any kind of letter from any language.
    // \p{M}: a character intended to be combined with another character (e.g. accents, umlauts, enclosing boxes, etc.).
    // \p{N}: any kind of numeric character in any script.

    // Other punctuation: 
    // \p{P}: any kind of punctuation character.
    // \p{Pd}: any kind of hyphen or dash.
    // \p{Ps}: any kind of opening bracket.
    // \p{Pe}: any kind of closing bracket.
    // \p{Pi}: any kind of opening quote.
    // \p{Pf}: any kind of closing quote.
    // \p{Po}: Other:  any kind of punctuation character that is not a dash, bracket, quote
    static protected $term_base_regexp = '\p{L}\p{M}\p{N}\p{Pi}\p{Pf}\p{Pd}\p{Po}'; //

    static protected $term_match_regexp = '/[`].*[`]/u'; // Regexp to match a regexp term

    //static protected $term_match_phrase = '/["][\p{L}\p{M}\p{N}\p{Pd}\p{Po} \'%]+["]/u'; // Unicode-safe Regexp to match an exact phrase term
    static protected $term_match_phrase = '/["][\p{L}\p{M}\p{N}\p{P} \'%]+["]/u'; // Unicode-safe Regexp to match an exact phrase term

    static protected $search_inputs = array(
        'search' => array(
            'label' => 'Search',
            'type'  => NULL, // primary
        ),
        'search_all' => array(
            'label' => 'All Words',
            'type'  => 'and',
        ),
        'search_any' => array(
            'label' => 'Any Words',
            'type'  => 'or',
        ),
        'search_one' => array(
            'label' => 'One of the Words',
            'type'  => 'xor',
        ),
        'search_none' => array(
            'label' => 'None of the Words',
            'type'  => 'not',
        ),
        'search_phrase' => array(
            'label' => 'Exact Phrase',
            'type'  => 'phrase',
        ),
        'search_regexp' => array(
            'label' => 'Regular Expression',
            'type'  => 'regexp',
        ),
        'search_boolean' => array(
            'label' => 'Boolean Expression',
            'type'  => 'boolean',
        ),
    );

    // Todo: Make Unicode safe by replacing with regexp \p{P}
    public $punctuation = array('.',',',':',';','\'','"','!','-','?','(',')','[',']');

    public function __construct($search = NULL, $options = [], $languages = []) 
    {
        $this->setSearch($search);
        $this->setOptions($options, TRUE);
        $this->languages = $languages;
    }

    /**
     * Create a new App\Search instance or returns FALSE if no search provided
     * @param string $search
     * @param array $options
     * @return App\Search|boolean
     */
    static public function parseSearch($search = NULL, $options = [], $languages = []) {
        if (empty($search)) {
            $has_search = FALSE;

            foreach(static::$search_inputs as $input => $settings) {
                if(!empty($options[$input])) {
                    $has_search = TRUE;
                    break;
                }
            }

            if(!$has_search) {
                return FALSE;
            }
        }

        return new static($search, $options, $languages);
    }

    /**
     * Sets the search query with minimal processing
     * @param string $search
     */
    public function setSearch($search) {
        $this->search = $search ? trim(preg_replace('/\s+/', ' ', $search)) : '';
    }

    /**
     * Sanitize the search term(s)
     */
    public function sanitize() {
        $this->search = $this->_sanitizeHelper($this->search, $this->search_type);

        foreach(static::$search_inputs as $input => $settings) {
            if(!empty($this->options[$input])) {
                $search_type = (array_key_exists('search_type', $settings)) ? $settings['search_type'] : $this->search_type;
                $this->options[$input] = $this->_sanitizeHelper($this->options[$input], $search_type);
            }
        }
    }

    protected function _sanitizeHelper($search, $search_type) {
        switch ($search_type) {
            case 'boolean':
            case 'regexp':
            case 'phrase':
                return $search; //
                break;
            default:
                return static::removeUnsafeCharacters($search);
        }
    }

    public static function removeUnsafeCharacters($search) 
    {
        // Need to ALLOW some punctuation but not others

        // \p{P}: any kind of punctuation character.
        // \p{Pd}: any kind of hyphen or dash.
        // \p{Ps}: any kind of opening bracket.
        // \p{Pe}: any kind of closing bracket.
        // \p{Pi}: any kind of opening quote.
        // \p{Pf}: any kind of closing quote.
        // \p{Po}: Other:  any kind of punctuation character that is not a dash, bracket, quote

        $search = preg_replace_callback('/\p{P}/', function($matches) {
            $p = $matches[0];

            // if(in_array($p, ['!','.','?',','])) {
            //     return $p; // BSS-193
            // }

            if(in_array($p, ['—','.',',',':',';','\'','"','!','-','?','(',')','[',']'])) {
                return ' ';
            }

            if(preg_match('/\p{Pi}/', $p)) {
                return $p;
            }

            if(preg_match('/\p{Pf}/', $p)) {
                return $p;
            }

            if(in_array($p, ['%', '*'])) {
                return $p;
            }

            if(preg_match('/\p{Pd}/', $p)) {
                return $p;
            }

            if(preg_match('/\p{Po}/', $p)) {
                return $p;
            }

            return ' ';
        }, $search);

        $other = ['—', '„', '“','”'];

        $search = str_replace($other, ' ', $search);
        $search = preg_replace('/\s{2,}/', ' ', $search);
        $search = trim($search);

        return $search;
    }

    /**
     * Validates the search term(s)
     */
    public function validate() {
        $valid = $this->_validateHelper($this->search, $this->search_type);

        foreach(static::$search_inputs as $input => $settings) {
            if(!empty($this->options[$input])) {
                $search_type = (array_key_exists('search_type', $settings)) ? $settings['search_type'] : $this->search_type;

                if(!$this->_validateHelper($this->options[$input], $search_type)) {
                    $valid = FALSE;
                }
            }
        }

        return $valid;
    }

    protected function _validateHelper($search, $search_type) {
        switch ($search_type) {
            case 'boolean':
            case 'all_words':
            case 'and':
                return $this->_validateBoolean($search);
                break;
            default:
                return TRUE;
        }
    }

    /**
     * Validates a Boolean search
     * @param type $search
     */
    protected function _validateBoolean($search) {
        $valid = TRUE;
        $skip_paren_check = (func_num_args() > 1) ? func_get_arg(1) : FALSE;
        
        if(!$skip_paren_check) {        
            $lpar = substr_count($search, '(');
            $rpar = substr_count($search, ')');

            if($lpar != $rpar) {
                $this->addError( trans('errors.paren_mismatch'), 4 );
                $valid = FALSE;
            }
        }

        $standardized = static::standardizeBoolean($search);
        $not_at_beg = ['AND', 'XOR', 'OR'];
        $not_at_end = ['AND', 'XOR', 'OR', 'NOT'];
        $len = strlen($standardized);
        $terms = static::parseQueryTerms($search);

        foreach($not_at_beg as $op) {
            if(strpos($standardized, $op) === 0) {
                $this->addError( trans('errors.operator.op_at_beginning', ['op' => $op]), 4);
                return FALSE;
            }
        }

        foreach($not_at_end as $op) {
            if(strrpos($standardized, $op) === $len - strlen($op)) {
                $this->addError( trans('errors.operator.op_at_end', ['op' => $op]), 4);
                return FALSE;
            }
        }

        return $valid;
    }

    /**
     * Sets the options with option to overwrite existing
     * @param array $options
     * @param bool $overwrite
     */
    public function setOptions($options, $overwrite = FALSE) {
        $current = ($overwrite) ? $this->options_default : $this->options;
        $this->options = array_replace_recursive($current, $options);
        $this->search_type = (isset($this->options['search_type'])) ? $this->options['search_type'] : 'and';
    }

    public function isBooleanSearch() {
        return ($this->search_type == 'boolean');
    }

    /**
     * Generates the WHERE clause portion from the search query
     * @return array|bool
     */
    public function generateQuery($binddata = [], $table_alias = '') {
        $search_type = (!empty($this->search_type)) ? $this->search_type : 'and';
        $search = $this->search;
        return $this->_generateQueryHelper($search, $search_type, $table_alias, TRUE, $binddata);
    }

    protected function _generateQueryHelper(
        $search, $search_type, $table_alias = '', $include_extra_fields = FALSE, 
        $binddata = [], $fields = ''
    ) {
        $searches = [];

        if($search) {
            $searches[] = $this->booleanizeQuery($search, $search_type);
        }

        if($include_extra_fields) {
            foreach(static::$search_inputs as $input => $settings) {
                if(!empty($settings['type']) && isset($this->options[$input])) {
                    $searches[] = $this->booleanizeQuery($this->options[$input], $settings['type']);
                }
            }
        }

        $searches = array_filter($searches); // remove empty values
        $count    = count($searches);

        if (!$count) {
            $this->search_parsed = '';
            return FALSE;
        }


        $raw_bool = ($count == 1) ? $searches[0] : '(' . implode(') & (', $searches) . ')';
        $std_bool = static::standardizeBoolean($raw_bool);

        $this->search_parsed = $std_bool;

        $term_list = static::parseQueryTerms($std_bool, TRUE);

        $this->terms = $terms = $term_list['all'];
        $operators = static::parseQueryOperators($std_bool, $terms);

        $sql = ' ' . $std_bool . ' ';

        if(static::containsInvalidCharacters($terms)) {
            $this->addError( trans('errors.invalid_search.general', ['search' => $search]), 4);
            return FALSE;
        }

        foreach($term_list as $type => $items) {
            if($type == 'all') {
                continue;
            }

            $last_term_pos = 0;

            foreach($items as $term) {
                list($term_sql, $bind_index) = $this->_termSql($term, $binddata, $fields, $table_alias);

                // We only want to replace it ONCE, in case it is used multiple times
                $term_pos = strpos($sql, $term, $last_term_pos);
                // $term_pos = strpos($sql, ' ' . $term . ' ', $last_term_pos);

                if($term_pos !== FALSE) {
                    // $term_pos ++;
                    $sql = substr_replace($sql, $term_sql, $term_pos, strlen($term));
                }

                $last_term_pos = $term_pos + strlen($term_sql);
            }
        }

        $sql = trim($sql);
        return array($sql, $binddata);
    }

    protected function _termSql($term, &$binddata = [], $fields = '', $table_alias = '') {
        $exact_case  = $this->options['exact_case'];
        $whole_words = $this->options['whole_words'];
        $exact_phrase = ($this->options['search_type'] == 'phrase');

        if($this->options['whole_words_debug']) {
            $whole_words = FALSE;
        }

        $sql = [];
        $fields    = $this->_termFields($term, $fields, $table_alias);
        $term_fmts = $this->_termFormat($term, $exact_phrase, $whole_words, FALSE);
        $term_ops  = $this->_termOperator($term, $exact_phrase, $whole_words, FALSE);

        foreach($fields as $field) {
            $sql_sub = [];

            foreach($term_fmts AS $key => $term_fmt) {
                $bind_index = static::pushToBindData($term_fmt, $binddata);
                $sql_sub[]  = $this->_assembleTermSql($field, $bind_index, $term_ops[$key], $exact_case);
            }

            $sql[] = implode(' AND ', $sql_sub);
        }

        $sql = (count($sql) == 1) ? '(' . $sql[0] . ')' : '(' . implode(' OR ', $sql) . ')';
        return array($sql, $bind_index);
    }

    protected function _termFields($term, $fields = '', $table_alias = '') {
        $fields = ($fields) ? $fields : $this->search_fields;
        $fields = explode(',', $fields);

        foreach($fields as &$field) {
            if($table_alias) {
                $field = (strpos($field, '.') !== FALSE) ? $field : $table_alias . '.' . $field;
            }

            $field = '`' . str_replace('.','`.`', $field) . '`'; // Use proper notation
        }

        return $fields;
    }

    protected function _termOperator($term, $exact_phrase = FALSE, $whole_words = FALSE, $primary_only = TRUE) {
        $is_regexp  = $this->_isRegexpSearch($term);
        $is_strongs = $this->_isStrongsSearch($term);

        // Other searches that use REGEXP
        $uses_regexp = ($this->_isPhraseSearch($term) || $whole_words || $is_strongs);

        if($is_regexp) {
            return ($primary_only) ? 'REGEXP' : ['REGEXP'];
        }

        $is_special = (static::isTermRegexp($term) || $exact_phrase);

        if($whole_words || $uses_regexp) {
            return ($primary_only) ? 'REGEXP' : ['LIKE', 'REGEXP'];
        }
        else {
            $op = ($is_special) ? 'REGEXP' : 'LIKE';
            return ($primary_only) ? $op : [$op];
        }
    }

    protected function _termFormat($term, $exact_phrase = FALSE, $whole_words = FALSE, $primary_only = TRUE) {
        $is_phrase = $is_regexp = $is_strongs = $uses_regexp = FALSE;
        $term_inexact = '%' . trim($term, '%"`\'') . '%';
        $phrase_whitespace = ' ';
        $phrase_whitespace = '([^a-fi-zA-FI-Z]+)';  // General approximation (fails open - may pull MORE results than it should)
        // $phrase_whitespace = "\]?[ {]([^a-fi-zA-FI-Z]*)"; // This prefered whitespace separator works in navicat but not in this software?

        // $primary_only = TRUE;

        // Regexp
        if($this->_isRegexpSearch($term)) {

            $term = trim($term, '`');
            $is_regexp = TRUE;
            $uses_regexp = TRUE;
            return ($primary_only) ? $term : [$term]; // Whole words ignored for regexp
        }

        // Phrases
        if($this->_isPhraseSearch($term)) {
            $term = trim($term, '"');
            $is_phrase = TRUE;
            $uses_regexp = TRUE;
            $term_inexact = str_replace(' ', '%', $term_inexact);
            // $whole_words = TRUE;

            if(!$whole_words) {
                $phrase_term = str_replace(' ', $phrase_whitespace, $term);
                // $phrase_term = '.*' . str_replace(' ', $phrase_whitespace, $term) . '.*';

                // to do - use this return if bible has no markup
                // return ($primary_only) ? $phrase_term : [$phrase_term];
                return ($primary_only) ? $phrase_term : [$term_inexact, $phrase_term];
            }
        }

        // Strongs number
        if($this->_isStrongsSearch($term)) {
            $is_strongs = TRUE;
            $whole_words = TRUE;
        }

        if(!$whole_words && !$uses_regexp) {
            $term = '%' . $term . '%';
            return ($primary_only) ? $term : [$term];
        }

        $terms = [$term_inexact];

        $has_st_pct = (strpos($term, '%') === 0);
        $has_en_pct = (strrpos($term, '%') === strlen($term) - 1);

        if($has_st_pct && $has_en_pct) {
            return ($primary_only) ? $term : [$term];
        }

        if(config('database.mysql.new_regexp')) {
            $pre  = ($has_st_pct) ? '' : '\\b';
            $post = ($has_en_pct) ? '' : '\\b';
        } else {
            $pre  = ($has_st_pct) ? '' : '([[:<:]]|[‹])';
            $post = ($has_en_pct) ? '' : '([[:>:]]|[›])';
        }

        $regexp_term = ($is_phrase) ? str_replace(' ', $phrase_whitespace, $term) : str_replace('%', '.*', trim($term, '%'));
        $regexp_term = $pre . trim($regexp_term, '%') . $post;

        if($primary_only) {
            return $regexp_term;
        }

        $terms[] = $regexp_term;
        return $terms;
    }

    protected function _termFormatForHighlight($term, $exact_case = FALSE, $whole_words = FALSE) 
    {
        $preformat = $this->_termFormat($term, FALSE, $whole_words);
        //$preformat = ($whole_words) ? $preformat : trim($preformat, '%');
        $preformat = trim($preformat, '%./');
        // $preformat = str_replace(['[[:<:]]', '[[:>:]]'], '\b', $preformat);

        if(config('database.mysql.new_regexp')) {
            $preformat = str_replace('\\\\b', '\b', $preformat);
        } else {
            $preformat = str_replace(['([[:<:]]|[‹])', '([[:>:]]|[›])'], '\b', $preformat);
        }

        $case_insensitive = ($exact_case) ? '' : 'i';
        // $case_insensitive = '';
        $term_format = '/' . $preformat . '/u' . $case_insensitive; // u for unicode
        return $term_format;
    }

    protected function _termFormatForHighlightLanguage($term, $exact = FALSE, $whole = FALSE, $lang = null, $default = null)
    {
        // return $default;

        $changes_made = false;

        $term_orig = $term;
        $accents = [];

        $latin_alphabet = [
            'bs', 'br', 'ca', 'kw', 'co', 'hr', 'da', 'nl', 'en', 'fo', 'fr', 'gl', 'de', 'ga', 'is', 'it', 'la', 
            'lb', 'li', 'lt', 'gv', 'nb', 'nn', 'no', 'oc', 'pl', 'pt', 'rm', 'ro', 'gd', 'cy', 'sk', 'sl', 
            'es', 'sv', 'wa', 'cy', 'fy', 'ak', 'bm', 'ny', 'ee', 'ff', 'hz', 'ig', 'ki', 'rw', 'kg', 'kj', 'lg', 
            'ln', 'lu', 'nd', 'ng', 'nr', 'rn', 'sn', 'st', 'sw', 'ss', 'tn', 'ts', 'tw', 've', 'wo', 'xh', 'yo', 
            'zu', 'kr', 'tr', 'et', 'fi', 'hu', 'se', 'cs', 'lv'
        ];

        if(in_array($lang, $latin_alphabet)) {
            $accents['a'] = ['ä', 'ă', 'ā', 'ã', 'å', 'ą'];
            $accents['c'] = ['ç', 'č', 'ć', 'ĉ', 'ċ'];
            $accents['d'] = ['ď', 'đ'];
            $accents['e'] = ['é','è', 'ê', 'ë', 'ē', 'ĕ', 'ė', 'ę', 'ě'];
            $accents['g'] = ['ĝ', 'ğ', 'ġ', 'ģ'];
            $accents['h'] = ['ĥ', 'ħ'];
            $accents['i'] = ['ï', 'ĩ', 'ī', 'ĭ', 'į', 'ı'];
            $accents['j'] = ['ĵ'];
            $accents['k'] = ['ķ', 'ĸ'];
            $accents['l'] = ['ĺ', 'ļ', 'ľ', 'ŀ', 'ł'];
            $accents['n'] = ['ñ', 'ń', 'ņ', 'ň', 'ŉ'];
            $accents['o'] = ['ŏ', 'ō', 'ő'];
            $accents['r'] = ['ŕ', 'ŗ', 'ř'];
            $accents['s'] = ['ş', 'ś', 'ŝ', 'ş', 'š'];
            $accents['t'] = ['ţ', 'ť', 'ŧ'];
            $accents['u'] = ['ŭ', 'ũ', 'ū', 'ŭ', 'ů', 'ű', 'ų'];
            $accents['w'] = ['ŵ'];
            $accents['y'] = ['ŷ'];
            $accents['z'] = ['ž', 'ź', 'ż', 'ž'];

            foreach($accents as $key => $l) {
                $l = $a = array_unique($l);

                $a[] = $key;

                if($lang == 'lv') {
                    // echo $term . PHP_EOL;
                }



                $term = str_replace($a, $key, $term);
                $term = str_replace($key, '[' . implode('', $a) . ']', $term);

                if($lang == 'lv') {
                    // echo $term . PHP_EOL .PHP_EOL;
                    // die();
                }

            }

            $changes_made = $term != $term_orig;
        }

        if($lang == 'lv') {
            // $term = str_replace('ā', 'a', $term);
            // $term = str_replace('a', '[āa]', $term);

            // $term = str_replace('č', 'c', $term);
            // $term = str_replace('c', '[čc]', $term);

            // $term = str_replace('ē', 'e', $term);
            // $term = str_replace('e', '[ēe]', $term);                        

            // $term = str_replace('ī', 'i', $term);
            // $term = str_replace('i', '[īi]', $term);                        

            // $term = str_replace('ķ', 'k', $term);
            // $term = str_replace('k', '[ķk]', $term);      

            // $term = str_replace('ļ', 'l', $term);
            // $term = str_replace('l', '[ļl]', $term);     

            // $term = str_replace('ņ', 'n', $term);
            // $term = str_replace('n', '[ņn]', $term);    

            // $term = str_replace('š', 's', $term);
            // $term = str_replace('s', '[šs]', $term);    

            // $term = str_replace('ū', 'u', $term);
            // $term = str_replace('u', '[ūu]', $term);

            // $term = str_replace('ž', 'z', $term);
            // $term = str_replace('z', '[žz]', $term); 
        }

        if(!$changes_made) {
            return $default;
        }

        return $this->_termFormatForHighlight($term, $exact, $whole);
    }

    protected function _assembleTermSql($field, $bind_index, $operator, $exact_case) 
    {
        //$binding = ($this->use_named_bindings) ? $bind_index : '?';

        $binding = $bind_index;
        $binary = ($exact_case) ? 'BINARY ' : '';
        return $binary . $field . ' ' . $operator . ' ' . $binding;
    }

    protected function _isRegexpSearch($term = NULL) 
    {
        if($term && static::isTermRegexp($term)) {
            return TRUE;
        }

        if($this->options['search_type'] == 'regexp') {
            return TRUE;
        }

        return FALSE;
    }

    protected function _isPhraseSearch($term = NULL) {
        if($term && static::isTermPhrase($term)) {
            return TRUE;
        }

        if($this->options['search_type'] == 'phrase') {
            return TRUE;
        }

        return FALSE;
    }

    protected function _isStrongsSearch($term = NULL) {
        if($term && static::isTermStrongs($term)) {
            return TRUE;
        }

        if($this->options['search_type'] == 'strongs') {
            return TRUE;
        }

        return FALSE;
    }

    public static function pushToBindData($item, &$binddata, $index_prefix = 'bd', $avoid_duplicates = FALSE) {
        if(!is_array($binddata)) {
            return FALSE;
        }
            
        $idx = ($avoid_duplicates) ? array_search($item, $binddata) : FALSE;

        if(config('app.query_use_named_placeholders')) {        

            if($idx === FALSE) {
                $idx = ':' . $index_prefix .  (count($binddata) + 1);
                $binddata[$idx] = $item;
            }
    
            return $idx;
        }
        else {
            if($idx === FALSE) {
                $binddata[] = $item;
                $idx = count($binddata) - 1;
            }

            return '?';
        }

    }

    public function booleanizeQuery($query, $search_type, $arg3 = NULL) {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        if ($search_type == 'boolean') {
            return $query;
        }

        $parsed = static::parseSimpleQueryTerms($query, $search_type);

        switch ($search_type) {
            case 'and':
            case 'all_words':
                // Do nothing
                break;
            case 'or':
            case 'any_word':
                $query = implode(' OR ', $parsed);
                break;
            case 'keyword_limit':
            case 'two_or_more':
                $limit = ($search_type == 'two_or_more') ? 2 : $this->options['keyword_limit'];
                $full  = static::parseQueryTerms($query);
                $query = static::buildTwoOrMoreQuery($full, $limit);
                break;
            case 'regexp':
                $query = '`' . trim($query, '"`\'') . '`';
                break;
            case 'phrase':
                $query = '"' . trim($query, '"`\'') . '"';
                break;
            case 'xor':
            case 'one_word':
                $query = implode(' XOR ', $parsed);
                break;
            case 'not':
                $query = 'NOT (' . $query . ')';
                break;
        }

        return $query;
    }

    /**
     * Parses out the terms of a boolean query
     * @param string $query standardized, booleanized query
     * @return array $parsed
     */
    public static function parseQueryTerms($query, $breakdown = FALSE) {
        $parsed = $phrases = $matches = [];
        // Remove operators that otherwise would be interpreted as terms
        $general = [];
        $find    = array(' AND ', ' XOR ', ' OR ', 'NOT ');
        $parsing = str_replace($find, ' ', $query);
        $phrases = static::parseQueryPhrases($query);
        $regexp  = static::parseQueryRegexp($query);

        $parsing = preg_replace(static::$term_match_phrase, '', $parsing); // Remove phrase terms once parsed
        $parsing = preg_replace(static::$term_match_regexp, '', $parsing); // Remove regexp terms once parsed

        preg_match_all('/%?[' . static::$term_base_regexp . '\']+%?/u', $parsing, $matches, PREG_SET_ORDER); // Unicode safe??

        foreach ($matches as $item) {
            $parsed[] = $item[0];
            $general[] = $item[0];
        }

        // $parsed = static::parseSimpleQueryTerms($parsing);

        $keyword_within_pr = FALSE;

        foreach ($phrases as $item) {
            $parsed[] = $item;

            if($breakdown && !$keyword_within_pr) {
                foreach($general as $g) {
                    if(strpos($item, $g) !== FALSE) {
                        $keyword_within_pr = TRUE;
                        break;
                    }
                }
            }
        }

        foreach($regexp as $item) {
            $parsed[] = $item;

            if($breakdown && !$keyword_within_pr) {
                foreach($general as $g) {
                    if(strpos($item, $g) !== FALSE) {
                        $keyword_within_pr = TRUE;
                        break;
                    }
                }
            }
        }

        if($breakdown) {
            $raw = [
                'all'       => $parsed,
                'general'   => $general,
                'phrases'   => $phrases,
                'regexp'    => $regexp,
            ];

            if($keyword_within_pr) {
                $size = [];

                // Sort arrays by size, decending
                foreach($raw as $key => &$d) {
                    Helpers::sortStringsByLength($d, 'DESC');
                    $size[$key] = strlen(json_encode($d));
                }
                unset($d);
                
                array_multisort($size, SORT_DESC, SORT_NUMERIC, $raw);
            }

            return $raw;
        }

        //$parsed = array_unique($parsed); // Causing breakage
        return $parsed;
    }

    /**
     * Gets the type of the term, to determine if it's something other than a simple keyword
     * @param string $term
     * @return string
     */
    public static function getTermType($term) {
        $type = 'keyword'; // Default type for non-special terms

        if(static::isTermPhrase($term)) {
            $type = 'phrase';
        }
        elseif(static::isTermRegexp($term)) {
            $type = 'regexp';
        }

        return $type;
    }

    public static function isTermSpecial($term) {
        return (static::getTermType($term) == 'keyword') ? FALSE : TRUE;
    }

    public static function isTermPhrase($term) {
        return ($term[0] == '"');
    }

    public static function isTermRegexp($term) {
        return ($term[0] == '`');
    }

    public static function isTermStrongs($term) {
        return FALSE;
    }

    /**
     * Parses out the terms of a boolean query
     * @param string $terms parsed query terms
     * @return bool
     */
    public static function containsInvalidCharacters($terms) {
        foreach($terms as $term) {
            if(static::isTermPhrase($term) || static::isTermRegexp($term)) {
                continue; // Ignore phrases and REGEXP
            }

            $invalid_chars = preg_replace('/[' . static::$term_base_regexp . '\(\)|!&^ "\'0-9%]+/u', '', $term);

            if(!empty($invalid_chars)) {
                return TRUE;
            }
        }

        return FALSE;
    }    

    public static function stripInvalidCharacters(&$terms) {
        foreach($terms as &$term) {
            if(static::isTermPhrase($term) || static::isTermRegexp($term)) {
                continue; // Ignore phrases and REGEXP
            }

            $term = preg_replace('/[' . static::$term_base_regexp . '\(\)|!&^ "\'0-9%]+/u', '', $term);
        }
        unset($term);
    }

    /**
     * Parses out the operators (and parenthensis) of a boolean query
     * @param string $query standardized, booleanized query
     * @return array $parsed
     */
    public static function parseQueryOperators($query, $terms = NULL) {
        $terms = ($terms) ? $terms : static::parseQueryTerms($query);
        $pre_parsed = str_replace($terms, '', $query);
        $pre_parsed = trim(preg_replace('/\s+/', ' ', $pre_parsed));
        return explode(' ', $pre_parsed);
    }

    public static function parseQueryPhrases($query, $underscore_map = FALSE) {
        return static::_parseQueryTermsSpecialHelpser($query, $underscore_map, static::$term_match_phrase);
    }

    public static function parseQueryRegexp($query, $underscore_map = FALSE) {
        return static::_parseQueryTermsSpecialHelpser($query, $underscore_map, static::$term_match_regexp);
    }

    protected static function _parseQueryTermsSpecialHelpser($query, $underscore_map, $matching) {
        $matches = $phrases = $underscores = [];
        preg_match_all($matching, $query, $matches, PREG_SET_ORDER);

        foreach ($matches as $item) {
            $phrase = $item[0];
            $phrases[] = $phrase;

            if($underscore_map) {
                $underscores[] = str_replace(' ', '_', $item[0]);
            }
        }

        if($underscore_map) {
            return array($phrases, $underscores);
        }
        else {
            return $phrases;
        }
    }

    /**
     * Parses out the terms of a simple (non-boolean) query
     * @param type $query
     * @return array $parsed
     */
    public static function parseSimpleQueryTerms($query, $search_type = 'and') {
        $parsed = $query ? explode(' ', $query) : [];
        $parsed = array_unique($parsed);
        return $parsed;
    }

    /**
     * Standardizes the boolean query, adds AND where implied
     * @param string $query
     * @return string
     */
    public static function standardizeBoolean($query) {
        // Standardise operators and replace them with a placeholder
        // Handles operator aliases    
        $and = [' AND ', '&&', '&'];
        $or  = [' OR ', '||', '|'];
        $not = ['NOT ', '!']; // BSS-193
        $xor = [' XOR ', '^^', '^'];

        $wildcard = ['*'];

        list($phrases, $underscored) = static::parseQueryPhrases($query, TRUE);  // Underscored stuff doesn't seem to be used.  remove?
        list($regexp, $regexp_uc) = static::parseQueryRegexp($query, TRUE);

        $phrase_placeholders = $regexp_placeholders = [];

        foreach($phrases as $key => $phrase) {
            $phrase_placeholders[] = 'ph' . $key . 'ph';
        }

        foreach($regexp as $key => $phrase) {
            $regexp_placeholders[] = 're' . $key . 're';
        }

        $query = str_replace($phrases, $phrase_placeholders, $query);
        $query = str_replace($regexp, $regexp_placeholders, $query);
        $query = str_replace($xor, ' ^ ', $query);
        $query = str_replace($and, ' & ', $query);
        $query = str_replace($or,  ' | ', $query);
        $query = str_replace($not, ' - ', $query);
        $query = str_replace($wildcard, '%', $query);
        $query = str_replace(array('(', ')'), array(' (', ') '), $query);
        // $query = str_replace('&  -', '-', $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));

        // strip invalid characters
        // $query = preg_replace('/[' . static::$term_base_regexp . '\(\)|!&^ "\'0-9%]+/u', '', $query);


        // Insert implied AND
        //$patterns = array('/\) [a-zA-Z0-9"]/', '/[a-zA-Z0-9"] \(/', '/[a-zA-Z0-9"] [a-zA-Z0-9"]/');
        // Note - this will break if we ever have
        $patterns = array(
            '/\) [\p{L}0-9\'%"]/u', // ") word"
            '/[\p{L}0-9\'%"] \(/u', // "word ("
            '/[\p{L}0-9\'%"] [\p{L}0-9\'%"]/u', // "word1 word2"
            '/[\p{L}]\) \(/u', // ""
        ); // Pre-unicode-safe

        $unicode_safe_base  = '\p{L}\p{M}\p{N}';
        $unicode_safe_base2 = '\p{L}\p{M}';
        $unicode_safe_base3 = '\p{P}';

            // Other punctuation: 
    // \p{P}: any kind of punctuation character.
    // \p{Pd}: any kind of hyphen or dash.
    // \p{Ps}: any kind of opening bracket.
    // \p{Pe}: any kind of closing bracket.
    // \p{Pi}: any kind of opening quote.
    // \p{Pf}: any kind of closing quote.
    // \p{Po}: Other:  any kind of punctuation character that is not a dash, bracket, quote

        $patterns = array(
            '/\) [' . $unicode_safe_base . '\'%"]/u',                               // ") word"
            '/[' . $unicode_safe_base . '\'%"] \(/u',                               // "word ("
            '/[' . $unicode_safe_base . '\'%"][' . $unicode_safe_base3 . '] [' . $unicode_safe_base . '\'%"]/u', // "word1<punctuation> word2"
            '/[' . $unicode_safe_base . '\'%"] [' . $unicode_safe_base . '\'%"]/u', // "word1 word2"
            '/[' . $unicode_safe_base . '\'%"] -/u',                                // "word -" ("word NOT")
            '/[' . $unicode_safe_base2 . ']\) \(/u',                                // "<punctuation> ("
        ); // Unicode safe, passing all unit tests

        // Pass 1
        $query = preg_replace_callback($patterns, function($matches) {
            return str_replace(' ', ' & ', $matches[0]);
        }, $query);        

        // Pass 2
        $query = preg_replace_callback($patterns, function($matches) {
            return str_replace(' ', ' & ', $matches[0]);
        }, $query);
        
        // Convert operator place holders into SQL operators
        $find  = array('&', '|', '-', '^');
        $repl  = array('AND', 'OR', ' NOT ', 'XOR');
        $query = str_replace($find, $repl, $query);
        $query = preg_replace('/AND\s*AND/', 'AND', $query); // Replace "AND AND" with "AND"
        $query = str_replace($phrase_placeholders, $phrases, $query);
        $query = str_replace($regexp_placeholders, $regexp, $query);
        //$query = str_replace($underscored, $phrases, $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));

        // $query = static::removeUnsafeCharacters($query);

        return $query;
    }

    public function __set($name, $value) {

    }

    public function __get($name) {
        $gettable = ['search', 'search_parsed', 'search_type', 'use_named_bindings', 'terms'];

        if ($name == 'search_parsed') {
            //return $this->parseSearchForQuery();
        }

        if (in_array($name, $gettable)) {
            return $this->$name;
        }
    }

    public function setUseNamedBindings($value) {
        $this->use_named_bindings = (bool) $value;
    }

    public function highlightResults($results, $highlight_tag = null) {
        $whole_word = $this->isTruthy('whole_words', $this->options);
        $exact_case = $this->isTruthy('exact_case',  $this->options);

        $highlight_tag = $highlight_tag ?: config('bss.defaults.highlight_tag');

        $terms = $this->terms;
        $terms_fmt = [];
        $pre = '&&';    // Regex safe, reused search alias
        $post = '%';    // Regex safe, reused search wildcard
        $pre_tag  = '<'  . $highlight_tag . '>';
        $post_tag = '</' . $highlight_tag . '>';
        // $pre_pattern  = '/' . $pre . '([^' . $pre . ' ]*)' . $pre .  '/'; // alt pattern
        // $post_pattern = '/' . $post . '([^' . $post . ' ]*)' .  $post .  '/';    // alt pattern    
        $pre_pattern  = '/' . $pre . '([^' . $pre . $post . ']*)' . $pre .  '/';
        $post_pattern = '/' . $post . '([^' . $pre . $post . ']*)' .  $post .  '/';
        $hl_word_pattern = '/' . $pre . '([^ ]+)' . $post . '/';

        Helpers::sortStringsByLength($terms, 'DESC');

        foreach($terms as $key => $term) {
            $terms_fmt[$key] = $this->_termFormatForHighlight($term, $exact_case, $whole_word);
        }

        foreach($results as $bible => &$verses) {
            $Bible = \App\Models\Bible::findByModule($bible);

            foreach($verses as &$verse) {
                if(isset($verse->_unmatched) && $verse->_unmatched) {
                    continue;
                }

                foreach($terms_fmt as $key => $term_fmt) {
                    $term_orig = $terms[$key];

                    // Apply language-specific term formatting, if any
                    $term_fmt = $this->_termFormatForHighlightLanguage($term_orig, $exact_case, $whole_word, $Bible->lang_short, $term_fmt);  

                    $verse->text = preg_replace_callback($term_fmt, function($matches) use ($pre, $post) {
                        return $pre . $matches[0] . $post;
                    }, $verse->text);
                }

                // Clean up
                $verse->text = preg_replace_callback($pre_pattern, function($matches) use ($pre, $post) {
                    return $pre . $matches[1];
                }, $verse->text);      

                $verse->text = preg_replace_callback($post_pattern, function($matches) use ($pre, $post) {
                    return $matches[1] . $post;
                }, $verse->text);

                $verse->text = preg_replace_callback($hl_word_pattern, function($matches) use ($pre, $post) {
                    return $pre . str_replace([$pre, $post], '', $matches[1]) . $post;
                }, $verse->text);

                $verse->text = str_replace([$pre, $post], [$pre_tag, $post_tag], $verse->text);
            }
            unset($verse);
        }
        unset($verses);

        return $results;
    }

    /**
     * Given a list of keywords, builds a query of $number or more
     * @param array $keywords list of keywords
     * @param int $number
     */
    static function buildTwoOrMoreQuery($keywords, $number, $glue = ' OR ') {
        $count = count($keywords);

        if($count == 1) {
            return implode(' OR ', $keywords);
        }

        if($count <= $number) {
            return implode(' AND ', $keywords);
        }

        $pieces = static::_buildTwoOrMoreQueryHelper($keywords, $number, $count);
        $query  = implode($glue, $pieces);
        return $query;
    }

    static function _buildTwoOrMoreQueryHelper($keywords, $number, $count) {
        $kw = $keywords; // $kw will be destroyed
        $big_pieces = [];

        if($number == 1) {
            return $keywords;
        }

        while($word = array_shift($kw)) {
            $pieces = static::_buildTwoOrMoreQueryHelper($kw, $number - 1, $count);

            foreach($pieces as $p) {
                $big_pieces[] = $word . ' AND ' . $p;
            }
        }

        return $big_pieces;
    }

    // Returns FALSE if is exact phrase or REGEXP search
    // Returns TRUE otherwise
    static public function isKeywordSearch($search_type) {

    }
}
