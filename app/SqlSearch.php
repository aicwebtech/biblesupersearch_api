<?php

namespace App;

/**
 * Class for searching SQL database given a string of keywords
 */
class SqlSearch {

    protected $search; // String containing the search keywords
    protected $search_parsed;
    protected $options = array();
    protected $options_default = array(
        'search_type' => 'AND',
        'whole_words' => FALSE,
    );

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
            return FALSE;
        } 
        else {
            return new static($search, $options);
        }
    }

    /**
     * Sets the search query with minimal processing
     * @param string $search
     */
    public function setSearch($search) {
        $this->search = trim(preg_replace('/\s+/', ' ', $search));
    }

    /**
     * Sets the options with option to overwrite existing
     * @param array $options
     * @param bool $overwrite
     */
    public function setOptions($options, $overwrite = FALSE) {
        $current = ($overwrite) ? $this->options_default : $this->options;
        $this->options = array_replace_recursive($current, $options);
    }

    /**
     * Parses the search and prepares it for the query
     * @return string
     */
    public function parseSearchForQuery() {
        $search_type = ($this->search_type) ? $this->search_type : 'and';
        $search = $this->search;
        $search = trim(preg_replace('/\s+/', ' ', $search));

        if (empty($search)) {
            $this->search_parsed = '';
            return '';
        }
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
        $find = array('AND', 'OR', 'XOR', 'NOT');
        $parsing = str_replace($find, ' ', $query);

        preg_match_all('/"[a-zA-z0-9 ]+"/', $parsing, $phrases, PREG_SET_ORDER);
        $parsing = preg_replace('/"[a-zA-z0-9 ]+"/', '', $parsing); // Remove phrases once parsed
        preg_match_all('/[a-zA-z0-9]+/', $parsing, $matches, PREG_SET_ORDER);

        foreach ($matches as $item) {
            $parsed[] = $item[0];
        }

        foreach ($phrases as $item) {
            $parsed[] = $item[0];
        }

        $parsed = array_unique($parsed);
        return $parsed;
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
        $and = array('AND', '*', '&&', '&');
        $or  = array('OR', '+', '||', '|');
        $not = array('NOT', '-', '!=', '-');
        $xor = array('XOR', '^^', '^');

        $query = str_replace($xor, ' ^ ', $query);
        $query = str_replace($and, ' & ', $query);
        $query = str_replace($or,  ' | ', $query);
        $query = str_replace($not, ' - ', $query);
        $query = str_replace(array('(', ')'), array(' (', ') '), $query);
        //$query = trim(preg_replace('/\s+/', ' ', $query));
        
        $patterns = array('/\) [a-zA-Z0-9]/', '/[a-zA-Z0-9] \(/', '/[a-zA-Z0-9] [a-zA-Z0-9]/');
        $query = preg_replace_callback($patterns, function($matches) {
            return str_replace(' ', ' AND ', $matches[0]);
        }, $query);
        
        // Convert operator place holders into SQL operators
        $find  = array('&', '|', '-', '^');
        $repl  = array('AND', 'OR', ' NOT ', 'XOR');
        $query = str_replace($find, $repl, $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));
        return $query;
    }

    public function __set($name, $value) {
        
    }

    public function __get($name) {
        $gettable = ['search', 'is_special'];

        if ($name = 'search_parsed') {
            return $this->parseSearchForQuery();
        }

        if (in_array($name, $gettable)) {
            return $this->$name;
        }
    }

}
