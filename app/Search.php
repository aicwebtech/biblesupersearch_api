<?php

namespace App;

class Search {
    use Traits\Error;
    
    public $search_type;
    public $search;
    public $whole_words = FALSE;
    protected $search_parsed;
    protected $is_special = FALSE;
    
    public function __construct($search = NULL, $search_type = NULL, $whole_words = FALSE) {
        $this->search = $search;
        $this->search_type = $search_type;
        $this->whole_words = $whole_words;
    }
    
    /**
     * Parses the search and prepares it for the query
     * @return string
     */
    public function parseSearch() {
        $search_type = ($this->search_type) ? $this->search_type : 'and';
        $search = $this->search;
        $search = trim( preg_replace('/\s+/', ' ', $search) );
        
        if(empty($search)) {
            $this->search_parsed = '';
            return '';
        }
        
        $this->is_special = $this->isSpecial($search, $search_type);
        
        if($this->is_special) {
            return $this->_parseSpecial($search, $search_type, $prox_limit);
        }
        else {
            return $this->_parseHelper($search, $search_type);
        }
    }
    
    protected function _parseHelper($search, $search_type) {
        
    }
    
    protected function _parseSpecial($search, $search_type, $prox_limit = 5) {
        
    }
    
    /**
     * Checks the given search query and type to see if it is a 'special' search
     * Special searches include proximity, within chapter and within book searches
     * These searches require special query handling
     * @param string $search
     * @return bool is_special
     */
    static public function isSpecial($search, $search_type) {
        // Check for special search identifiers
        $special_ops = [' PROX(', ' CHAP ', ' BOOK '];
        $special_types = ['proximity', 'chapter', 'book']; // strongs??
        $is_special = (in_array($search_type, $special_types)) ? TRUE : FALSE;
        
        if(!$is_special && $search_type == 'boolean') {            
            foreach($special_ops AS $op) {
                if(strpos($search, $op) !== FALSE) {
                    $is_special = TRUE;
                    break;
                }
            }
        }
        
        return $is_special;
    }
    
    public function __get($name) {
        $gettable = ['search', 'is_special'];
        
        if($name = 'search_parsed') {
            return $this->parseSearch();
        }
        
        if(in_array($name, $gettable)) {
            return $this->$name;
        }
    }
}
