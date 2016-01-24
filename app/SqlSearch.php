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
        if(empty($search)) {
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
        $this->search = trim( preg_replace('/\s+/', ' ', $search) );
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
        $search = trim( preg_replace('/\s+/', ' ', $search) );
        
        if(empty($search)) {
            $this->search_parsed = '';
            return '';
        }
    }
    
    public function __set($name, $value) {
        
    }
    
    public function __get($name) {
        $gettable = ['search', 'is_special'];
        
        if($name = 'search_parsed') {
            return $this->parseSearchForQuery();
        }
        
        if(in_array($name, $gettable)) {
            return $this->$name;
        }
    }
}
