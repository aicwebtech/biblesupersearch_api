<?php

namespace App;
use App\SqlSearch;

/**
 * Class for Bible database searches using a given set of keywords
 * Implements special searches that cannot be done with a single query: 
 *      proximity, within chapter, within book
 */

class Search extends SqlSearch {
    use Traits\Error;
    
    protected $is_special = FALSE;
    
    /**
     * Parses the search and prepares it for the query
     * Overrides parent
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
    
    public static function booleanizeQuery($query, $search_type, $arg3 = NULL) {
        $query = trim( preg_replace('/\s+/', ' ', $query) );
        
        if($search_type == 'boolean') {
            return $query;
        }
        
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
        $parsing = preg_replace('/PROX\([0-9]+\)/', ' ', $parsing);
        return parent::parseQueryTerms($parsing);
    }
    
    /**
     * Standardizes the boolean query, adds AND where implied
     * @param string $query
     * @return string
     */
    public static function standardizeBoolean($query) {
        $prox = array('~', 'PROX');
        // Todo - these can be search terms - how to resolve?
        $chap = array('CHAPTER', 'CHAP');
        $book = array('BOOK');
        
        $query = str_replace($prox, ' ~p~ ', $query);
        $query = str_replace($chap, ' ~c~ ', $query);
        $query = str_replace($book, ' ~b~ ', $query);
        
        $query = parent::standardizeBoolean($query);
        
        $find = array('~p~', '~c~', '~b~');
        $repl = array('PROX', 'CHAP', 'BOOK');
        $query = str_replace($find, $repl, $query);
        $query = str_replace('PROX (', 'PROX(', $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));
        
        return $query;
    }
}
