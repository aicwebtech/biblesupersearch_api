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
    protected $options = array();
    protected $languages = array();
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
    // \p{Pi}: any kind of opening quote.
    // \p{Pf}: any kind of closing quote.
    // \p{Pd}: any kind of hyphen or dash.
    static protected $term_base_regexp = '\p{L}\p{M}\p{N}\p{Pi}\p{Pf}\p{Pd}'; //

    static protected $term_match_regexp = '/[`].*[`]/u'; // Regexp to match a regexp term
    // static protected $term_match_phrase = '/["][\p{L}0-9 \'%]+["]/u'; // Regexp to match an exact phrase term
    static protected $term_match_phrase = '/["][\p{L}\p{M}\p{N} \'%]+["]/u'; // Unicode-safe Regexp to match an exact phrase term

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

    public function __construct($search = NULL, $options = array()) {
        $this->options_default['highlight_tag'] = config('bss.defaults.highlight_tag');
        $this->setSearch($search);
        $this->setOptions($options, TRUE);
    }

    /**
     * Create a new App\Search instance or returns FALSE if no search provided
     * @param string $search
     * @param array $options
     * @return App\Search|boolean
     */
    static public function parseSearch($search = NULL, $options = array()) {
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

        return new static($search, $options);
    }

    /**
     * Sets the search query with minimal processing
     * @param string $search
     */
    public function setSearch($search) {
        $this->search = trim(preg_replace('/\s+/', ' ', $search));
    }

    /**
     * Validates the search term(s)
     */
    public function validate() {
        $valid = TRUE;

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
        $lpar = substr_count($search, '(');
        $rpar = substr_count($search, ')');

        if($lpar != $rpar) {
            $this->addError( trans('errors.paren_mismatch'), 4 );
            $valid = FALSE;
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

    /**
     * Generates the WHERE clause portion from the search query
     * @return array|bool
     */
    public function generateQuery($binddata = array(), $table_alias = '') {
        $search_type = (!empty($this->search_type)) ? $this->search_type : 'and';
        $search = $this->search;
        return $this->_generateQueryHelper($search, $search_type, $table_alias, TRUE, $binddata);
    }

    protected function _generateQueryHelper($search, $search_type, $table_alias = '', $include_extra_fields = FALSE, $binddata = array(), $fields = '') {
        $searches   = array();

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
        $this->terms = $terms = static::parseQueryTerms($std_bool);
        //$operators = static::parseQueryOperators($std_bool, $terms);
        $sql = $std_bool;

        if(static::containsInvalidCharacters($terms)) {
            $this->addError( trans('errors.invalid_search.general', ['search' => $search]), 4);
            return FALSE;
        }

        foreach($terms as $term) {
            list($term_sql, $bind_index) = $this->_termSql($term, $binddata, $fields, $table_alias);
            //$sql = str_replace($term, $term_sql, $sql);
            // We only want to replace it ONCE, in case it is used multiple times
            $term_pos = strpos($sql, $term);

            if($term_pos !== FALSE) {
                $sql = substr_replace($sql, $term_sql, $term_pos, strlen($term));
            }
        }

        return array($sql, $binddata);
    }

    protected function _termSql($term, &$binddata = array(), $fields = '', $table_alias = '') {
        $exact_case  = $this->options['exact_case'];
        $whole_words = $this->options['whole_words'];
        $exact_phrase = ($this->options['search_type'] == 'phrase') ? TRUE : FALSE;

        if($this->options['whole_words_debug']) {
            $whole_words = FALSE;
        }

        $sql = array();
        $fields    = $this->_termFields($term, $fields, $table_alias);
        $term_fmts = $this->_termFormat($term, $exact_phrase, $whole_words, FALSE);
        $term_ops  = $this->_termOperator($term, $exact_phrase, $whole_words, FALSE);

        foreach($fields as $field) {
            $sql_sub = array();

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
        //$is_special = ($exact_phrase || static::isTermPhrase($term) || static::isTermRegexp($term)) ? TRUE : FALSE;
        $is_regexp   = ($this->_isRegexpSearch($term));

        $is_strongs = $this->_isStrongsSearch($term);

        if($is_strongs) {

        }

        // Other searches that use REGEXP
        $uses_regexp = ($this->_isPhraseSearch($term) || $whole_words || $is_strongs) ? TRUE : FALSE;

        if($is_regexp) {
            return ($primary_only) ? 'REGEXP' : ['REGEXP'];
        }

        $is_special = (static::isTermRegexp($term) || $exact_phrase) ? TRUE : FALSE;

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
        $phrase_whitespace = '[[:>:]]\s|(\{.*\})[[:<:]]';
        $phrase_whitespace = '([^a-fi-zA-FI-Z]+)';  // General approximation (fails open - may pull MORE results than it should)
        // $phrase_whitespace = "\]?[ {]([^a-fi-zA-FI-Z]*)"; // This prefered whitespace separator works in navicat but not in this software?

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

            if(!$whole_words) {
                $phrase_term = str_replace(' ', $phrase_whitespace, $term);

                // to do - use this return if bible has no markup
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

        $has_st_pct = (strpos($term, '%') === 0) ? TRUE : FALSE;
        $has_en_pct = (strrpos($term, '%') === strlen($term) - 1) ? TRUE : FALSE;

        if($has_st_pct && $has_en_pct) {
            return ($primary_only) ? $term : [$term];
        }

        $pre  = ($has_st_pct) ? '' : '[[:<:]]';
        $post = ($has_en_pct) ? '' : '[[:>:]]';
        $pre  = ($has_st_pct) ? '' : '([[:<:]]|[‹])';
        $post = ($has_en_pct) ? '' : '([[:>:]]|[›])';
        $regexp_term = ($is_phrase) ? str_replace(' ', $phrase_whitespace, $term) : str_replace('%', '.*', trim($term, '%'));
        $regexp_term = $pre . trim($regexp_term, '%') . $post;

        // die($regexp_term);

        if($primary_only) {
            return $regexp_term;
        }

        $terms[] = $regexp_term;
        return $terms;
    }

    protected function _termFormatForHighlight($term, $exact_case = FALSE, $whole_words = FALSE) {
        $preformat = $this->_termFormat($term, FALSE, $whole_words);
        //$preformat = ($whole_words) ? $preformat : trim($preformat, '%');
        $preformat = trim($preformat, '%./');
        // $preformat = str_replace(['[[:<:]]', '[[:>:]]'], '\b', $preformat);
        $preformat = str_replace(['([[:<:]]|[‹])', '([[:>:]]|[›])'], '\b', $preformat);
        $case_insensitive = ($exact_case) ? '' : 'i';
        $term_format = '/' . $preformat . '/' . $case_insensitive;
        return $term_format;
    }

    protected function _assembleTermSql($field, $bind_index, $operator, $exact_case) {
        //$binding = ($this->use_named_bindings) ? $bind_index : '?';

        $binding = $bind_index;
        $binary = ($exact_case) ? 'BINARY ' : '';
        return $binary . $field . ' ' . $operator . ' ' . $binding;
    }

    protected function _isRegexpSearch($term = NULL) {
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

        if($idx === FALSE) {
            $idx = ':' . $index_prefix .  (count($binddata) + 1);
            //$idx = count($binddata);
            $binddata[$idx] = $item;
        }

        return $idx;
    }

    public function booleanizeQuery($query, $search_type, $arg3 = NULL) {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        if ($search_type == 'boolean') {
            return $query;
        }

        $parsed = static::parseSimpleQueryTerms($query);

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
                $query = '`' . $query . '`';
                break;
            case 'phrase':
                $query = '"' . $query . '"';
                break;
            case 'xor':
            case 'one_word':
                $query = implode(' XOR ', $parsed);
                break;
            case 'not':
                $query = 'NOT (' . $query . ')';
                //$query = 'NOT ' . implode(' NOT ', $parsed);
                break;
        }

        return $query;
    }

    /**
     * Parses out the terms of a boolean query
     * @param string $query standardized, booleanized query
     * @return array $parsed
     */
    public static function parseQueryTerms($query) {
        $parsed = $phrases = $matches = array();
        // Remove operators that otherwise would be interpreted as terms
        $find    = array(' AND ', ' XOR ', ' OR ', 'NOT ');
        $parsing = str_replace($find, ' ', $query);
        $phrases = static::parseQueryPhrases($query);
        $regexp  = static::parseQueryRegexp($query);

        $parsing = preg_replace(static::$term_match_phrase, '', $parsing); // Remove phrase terms once parsed
        $parsing = preg_replace(static::$term_match_regexp, '', $parsing); // Remove regexp terms once parsed

//        preg_match_all('/%?[\p{L}0-9\']+%?/u', $parsing, $matches, PREG_SET_ORDER);
        preg_match_all('/%?[' . static::$term_base_regexp . '\']+%?/u', $parsing, $matches, PREG_SET_ORDER); // Unicode safe??

        foreach ($matches as $item) {
            $parsed[] = $item[0];
        }

        // $parsed = static::parseSimpleQueryTerms($parsing);

        foreach ($phrases as $item) {
            $parsed[] = $item;
        }

        foreach($regexp as $item) {
            $parsed[] = $item;
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
        return ($term{0} == '"') ? TRUE : FALSE;
    }

    public static function isTermRegexp($term) {
        return ($term{0} == '`') ? TRUE : FALSE;
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
        $matches = $phrases = $underscores = array();
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
    public static function parseSimpleQueryTerms($query) {
        $parsed = explode(' ', $query);
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
        $and = array(' AND ', '*', '&&', '&');
        $or  = array(' OR ', '+', '||', '|');
        $not = array('NOT ', '!');
        $xor = array(' XOR ', '^^', '^');

        list($phrases, $underscored) = static::parseQueryPhrases($query, TRUE);  // Underscored stuff doesn't seem to be used.  remove?
        list($regexp, $regexp_uc) = static::parseQueryRegexp($query, TRUE);

        $phrase_placeholders = $regexp_placeholders = array();

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
        $query = str_replace(array('(', ')'), array(' (', ') '), $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));

        // Insert implied AND
        //$patterns = array('/\) [a-zA-Z0-9"]/', '/[a-zA-Z0-9"] \(/', '/[a-zA-Z0-9"] [a-zA-Z0-9"]/');
        // Note - this will break if we ever have
        $patterns = array(
            '/\) [\p{L}0-9\'%"]/u',
            '/[\p{L}0-9\'%"] \(/u',
            '/[\p{L}0-9\'%"] [\p{L}0-9\'%"]/u',
            '/[\p{L}]\) \(/u',
        ); // Pre-unicode-safe

        $unicode_safe_base  = '\p{L}\p{M}\p{N}';
        $unicode_safe_base2 = '\p{L}\p{M}';

        $patterns = array(
            '/\) [' . $unicode_safe_base . '\'%"]/u',
            '/[' . $unicode_safe_base . '\'%"] \(/u',
            '/[' . $unicode_safe_base . '\'%"] [' . $unicode_safe_base . '\'%"]/u',
            '/[' . $unicode_safe_base2 . ']\) \(/u',
        ); // Unicode safe, passing all unit tests

        //$patterns = array('/\) [\p{L}0-9"\']/u', '/[\p{L}0-9"\'] \(/u', '/[\p{L}0-9] [\p{L}0-9]/u', '/["\'] [\p{L}0-9"\']/u');  // OLD??

        $query = preg_replace_callback($patterns, function($matches) {
            return str_replace(' ', ' & ', $matches[0]);
        }, $query);

        // Convert operator place holders into SQL operators
        $find  = array('&', '|', '-', '^');
        $repl  = array('AND', 'OR', ' NOT ', 'XOR');
        $query = str_replace($find, $repl, $query);
        $query = str_replace($phrase_placeholders, $phrases, $query);
        $query = str_replace($regexp_placeholders, $regexp, $query);
        //$query = str_replace($underscored, $phrases, $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));
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
        $this->use_named_bindings = ($value) ? TRUE : FALSE;
    }

    public function highlightResults($results) {
        $whole_word = $this->isTruthy('whole_words', $this->options);
        $exact_case = $this->isTruthy('exact_case',  $this->options);
        //var_dump($this->options);

        $terms = $this->terms;
        $terms_fmt = array();
        $pre  = '<' . $this->options['highlight_tag'] . '>';
        $post = '</' . $this->options['highlight_tag'] . '>';

        foreach($terms as $key => $term) {
            $terms_fmt[$key] = $this->_termFormatForHighlight($term, $exact_case, $whole_word);
        }

        foreach($results as $bible => &$verses) {
            foreach($verses as &$verse) {
                foreach($terms_fmt as $key => $term_fmt) {
                    $term = $terms[$key];
                    $verse->text = preg_replace_callback($term_fmt, function($matches) use ($pre, $post) {
                        return $pre . $matches[0] . $post;
                    }, $verse->text);
                }
            }
        }

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
}
