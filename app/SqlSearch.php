<?php

namespace App;

/**
 * Class for searching SQL database given a string of keywords
 */
class SqlSearch {
    use Traits\Error;
    
    protected $search; // String containing the search keywords
    protected $search_parsed;
    protected $options = array();
    protected $search_type = 'and';
    protected $options_default = array(
        'search_type' => 'and',
        'whole_words' => FALSE,
    );
    
    //protected $use_unnamed_bindings = FALSE;
    protected $use_named_bindings = FALSE;
    public $search_fields = 'text'; // Comma separated
    
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
    
    public $punctuation = array('.',',',':',';','\'','"','!','-','?','(',')','[',']');

    public function __construct($search = NULL, $options = array()) {
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
            case 'boolean' :
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
            $this->addError( trans('errors.paren_mismatch') );
            $valid = FALSE;
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
            $searches[] = static::booleanizeQuery($search, $search_type);
        }
        
        if($include_extra_fields) {            
            foreach(static::$search_inputs as $input => $settings) {
                if(!empty($settings['type']) && isset($this->options[$input])) {
                    $searches[] = static::booleanizeQuery($this->options[$input], $settings['type']);
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
        $terms = static::parseQueryTerms($std_bool);
        $sql = $std_bool;
        
        foreach($terms as $term) {
            list($term_sql, $bind_index) = $this->_termSql($term, $binddata, $fields, $table_alias);
            $sql = str_replace($term, $term_sql, $sql);
        }

        return array($sql, $binddata);
    }
    
    protected function _termSql($term, &$binddata = array(), $fields = '', $table_alias = '') {        
        $exact_case  = (array_key_exists('exact_case',  $this->options))  ? $this->options['exact_case'] : FALSE;
        $exact_case  = ($exact_case  && $exact_case  !== 'false') ? TRUE : FALSE;
        $whole_words = (array_key_exists('whole_words',  $this->options)) ? $this->options['whole_words'] : FALSE;
        $whole_words = ($whole_words && $whole_words !== 'false') ? TRUE : FALSE;
        
        $fields = $this->_termFields($term, $fields, $table_alias);
        $op = $this->_termOperator($term, $exact_case, $whole_words);
        $term_fmt = $this->_termFormat($term, $exact_case, $whole_words);
        $bind_index = static::pushToBindData($term_fmt, $binddata);
        $sql = array();
        
        foreach($fields as $field) {
            //$sql[] = ($whole_words) ? $this->_assembleTermSqlWholeWords($field, $bind_index, $op) : $this->_assembleTermSql($field, $bind_index, $op);
            $sql[] = $this->_assembleTermSql($field, $bind_index, $op, $exact_case);
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
    
    protected function _termOperator($term, $exact_case = FALSE, $whole_words = FALSE) {
        if($whole_words) {
            return 'REGEXP';
        }
        else {
            return (strpos($term, ' ') !== FALSE) ? 'REGEXP' : 'LIKE';
        }
    }
    
    protected function _termFormat($term, $exact_case = FALSE, $whole_words = FALSE) {
        if(strpos($term, ' ') !== FALSE) {
            return trim($term, '"');
        }
        
        if(!$whole_words) {
            return '%' . $term . '%';
        }
        
        $has_st_pct = (strpos($term, '%') === 0) ? TRUE : FALSE;
        $has_en_pct = (strrpos($term, '%') === strlen($term) - 1) ? TRUE : FALSE;
        
        if($has_st_pct && $has_en_pct) {
            return $term;
        }
        
        //var_dump($term);
        
        $pre  = ($has_st_pct) ? '/' : '[[:<:]]';
        $post = ($has_en_pct) ? '.' : '[[:>:]]';
        
        return $pre . str_replace('%', '.', trim($term, '%')) . $post;
        
        //return ($whole_words) ? '[[:<:]]' . str_replace('%', '/', $term) . '[[:>:]]' : '%' . $term . '%';
    }
    
    protected function _assembleTermSql($field, $bind_index, $operator, $exact_case) {
        //$binding = ($this->use_named_bindings) ? $bind_index : '?';
        $binding = $bind_index;
        $binary = ($exact_case) ? 'BINARY ' : '';
        return $binary . $field . ' ' . $operator . ' ' . $binding;
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
    
    public static function booleanizeQuery($query, $search_type, $arg3 = NULL) {
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
            case 'phrase':
            case 'regexp':
                $query = '"' . $query . '"';
                break;
            case 'xor':
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
        $find = array(' AND ', ' XOR ', ' OR ', 'NOT ');
        $parsing = str_replace($find, ' ', $query);

        //preg_match_all('/"[a-zA-z0-9 ]+"/', $parsing, $phrases, PREG_SET_ORDER);
        $phrases = static::parseQueryPhrases($query);
        //$parsing = preg_replace('/"[a-zA-z0-9 ]+"/', '', $parsing); // Remove phrases once parsed
        $parsing = preg_replace('/"[\p{L}0-9 ]+"/', '', $parsing); // Remove phrases once parsed
        //preg_match_all('/%?[a-zA-Z0-9]+%?/', $parsing, $matches, PREG_SET_ORDER);
        preg_match_all('/%?[\p{L}0-9]+%?/u', $parsing, $matches, PREG_SET_ORDER);

        foreach ($matches as $item) {
            $parsed[] = $item[0];
        }

        foreach ($phrases as $item) {
            //$parsed[] = $item[0];
            $parsed[] = $item;
        }

        //$parsed = array_unique($parsed); // Causing breakage
        return $parsed;
    }
    
    public static function parseQueryPhrases($query, $underscore_map = FALSE) {
        $matches = $phrases = $underscores = array();
        //preg_match_all('/"[a-zA-z0-9 ]+"/', $query, $matches, PREG_SET_ORDER);
        preg_match_all('/"[\p{L}0-9 ]+"/', $query, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $item) {
            $phrases[] = $item[0];
            
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
        $not = array('NOT ', '-', '!=');
        $xor = array(' XOR ', '^^', '^');
        
        list($phrases, $underscored) = static::parseQueryPhrases($query, TRUE);
        $query = str_replace($phrases, $underscored, $query);
        $query = str_replace($xor, ' ^ ', $query);
        $query = str_replace($and, ' & ', $query);
        $query = str_replace($or,  ' | ', $query);
        $query = str_replace($not, ' - ', $query);
        $query = str_replace(array('(', ')'), array(' (', ') '), $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));

        //$patterns = array('/\) [a-zA-Z0-9"]/', '/[a-zA-Z0-9"] \(/', '/[a-zA-Z0-9"] [a-zA-Z0-9"]/');
        $patterns = array('/\) [\p{L}0-9"]/', '/[\p{L}0-9"] \(/', '/[\p{L}0-9"] [\p{L}0-9"]/');
        $query = preg_replace_callback($patterns, function($matches) {
            return str_replace(' ', ' & ', $matches[0]);
        }, $query);
        
        // Convert operator place holders into SQL operators
        $find  = array('&', '|', '-', '^');
        $repl  = array('AND', 'OR', ' NOT ', 'XOR');
        $query = str_replace($find, $repl, $query);
        $query = str_replace($underscored, $phrases, $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));
        return $query;
    }

    public function __set($name, $value) {
        
    }

    public function __get($name) {
        $gettable = ['search', 'search_parsed', 'search_type', 'use_named_bindings'];
        
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
}
