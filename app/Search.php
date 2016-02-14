<?php

namespace App;
use App\SqlSearch;

/**
 * Class for Bible database searches using a given set of keywords
 * Implements special searches that cannot be done with a single query: 
 *      proximity, within chapter, within book
 */

class Search extends SqlSearch {
    
    protected $is_special = FALSE;
    
    /**
     * Sets the search query with minimal processing
     * @param string $search
     */
    public function setSearch($search) {
        parent::setSearch($search);
        $this->is_special = static::isSpecial($search, $this->search_type);
    }
    
    public function setOptions($options, $overwrite = FALSE) {
        parent::setOptions($options, $overwrite);
        $this->is_special = static::isSpecial($this->search, $this->search_type);
    }
    
    /**
     * Parses the search and prepares it for the query
     * Overrides parent
     * @return string
     * Is this needed? Remove?
     */
    public function _generateQuery() {
        $search_type = ($this->search_type) ? $this->search_type : 'and';
        $search = $this->search;
        
        //$this->is_special = $this->isSpecial($search, $search_type);
        
        if($this->is_special) {
            //return $this->_generateProximityQuery($search, $search_type);
        }
        else {
            return $this->_generateQueryHelper($search, $search_type, TRUE);
        }
    }
    
    protected function _parseHelper($search, $search_type) {
        
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
        $special_ops   = ['~p', '~c', '~b', '~l'];
        $special_types = ['proximity', 'chapter', 'book']; // strongs??
        $is_special    = (in_array($search_type, $special_types)) ? TRUE : FALSE;
        
        if(!$is_special && $search_type == 'boolean') {            
            $search = static::standardizeProximityOperators($search);
            
            foreach($special_ops AS $op) {
                if(strpos($search, $op) !== FALSE) {
                    $is_special = TRUE;
                    break;
                }
            }
        }
        
        return $is_special;
    }
    
    public static function booleanizeQuery($query, $search_type, $arg3 = NULL) {
        if($search_type == 'boolean') {
            return $query;
        }
        
        $query = trim( preg_replace('/\s+/', ' ', $query) );
        $parsed = static::parseSimpleQueryTerms($query);
        
        switch($search_type) {
            case 'strongs':
                // Do nothing
                break;
            case 'proximity':
                $query = implode(' PROX(' . $arg3 . ') ', $parsed);
                break;
            case 'chapter':
                $query = implode(' CHAP ', $parsed);
                break;
            case 'book':
                $query = implode(' BOOK ', $parsed);
                break;
            default:
                return parent::booleanizeQuery($query, $search_type);
        }
        
        return $query;
    }
    
    /**
     * Parses out the terms of a boolean query
     * @param string $query standardized, booleanized query
     * @return array $parsed 
     */
    public static function parseQueryTerms($query) {
        // Remove operators that otherwise would be interpreted as terms
        $find   = array('CHAPTER', 'CHAP', 'BOOK');
        $parsing = str_replace($find, ' ', $query);
        $parsing = preg_replace('/PROXC\([0-9]+\)/', ' ', $parsing);
        $parsing = preg_replace('/PROX\([0-9]+\)/',  ' ', $parsing);
        $parsing = preg_replace('/PROC\([0-9]+\)/',  ' ', $parsing);
        return parent::parseQueryTerms($parsing);
    }
    
    /**
     * Standardizes the boolean query, adds AND where implied
     * @param string $query
     * @return string
     */
    public static function standardizeBoolean($query) {        
        $query = static::standardizeProximityOperators($query, '~ ');
        $query = parent::standardizeBoolean($query);
        
        $find = array('~p~', '~c~', '~b~', '~l~');
        $repl = array('PROX', 'CHAP', 'BOOK', 'PROC');
        $query = str_replace($find, $repl, $query);
        $query = str_replace('PROX (', 'PROX(', $query);
        $query = str_replace('PROC (', 'PROC(', $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));
        
        return $query;
    }
    
    public static function standardizeProximityOperators($query, $suffix = '') {
        $proc = array('PROC','PROXC'); // Proximity - force within same chapter (Legacy 2.x functionality)
        $prox = array('PROX'); // Proximity - within same book
        // Todo - these can be search terms - how to resolve?
        $chap = array('CHAPTER', 'CHAP');
        $book = array('BOOK');
        
        $query = str_replace('~',   ' ~p' . $suffix, $query);
        $query = str_replace($proc, ' ~l' . $suffix, $query);
        $query = str_replace($prox, ' ~p' . $suffix, $query);
        $query = str_replace($chap, ' ~c' . $suffix, $query);
        $query = str_replace($book, ' ~b' . $suffix, $query);
        return $query;
    }
    
    public function parseProximitySearch() {
        if(!$this->is_special) {
            return FALSE;
        }
        
        $limit  = (isset($this->options['proximity_limit'])) ? intval($this->options['proximity_limit']) : 5;
        $search = static::booleanizeQuery($this->search, $this->search_type, $limit);
        $unexploded = static::standardizeProximityOperators($search);
        $Searches = $operators = $matches = array();
        
        preg_match_all('/~p(\([0-9]+\))?/', $unexploded, $matches['prox'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        preg_match_all('/~l(\([0-9]+\))?/', $unexploded, $matches['proc'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        preg_match_all('/~c/',              $unexploded, $matches['chap'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        preg_match_all('/~b/',              $unexploded, $matches['book'], PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
        
        foreach($matches as $k => $ar) {
            foreach($ar as $match) {
                $item = $match[0][0];
                $operators[$match[0][1]] = $item;
            }
        }
        
        ksort($operators);
        $operators = array_values($operators);
        $parsed    = str_replace($operators, '~~', $unexploded);
        $split     = explode('~~', $parsed);
        
        foreach($split as $separate) {
            $Search = static::parseSearch($separate, $this->options);
            $Searches[] = $Search;
        }
        
        return array($Searches, $operators);
    }
    
    public function __get($name) {
        $gettable = ['is_special'];

        if (in_array($name, $gettable)) {
            return $this->$name;
        }
        
        return parent::__get($name);
    }
}
