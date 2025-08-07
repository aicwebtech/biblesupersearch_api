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
        $is_special    = (in_array($search_type, $special_types));

        if(!$is_special && ($search_type == 'boolean' || $search_type == 'and')) {
            $is_special = static::containsProximityOperators($search);
        }

        return $is_special;
    }

    static public function containsProximityOperators($search) {
        $special_ops   = ['~p', '~c', '~b', '~l'];
        $search = (string)static::standardizeProximityOperators($search);
        $prox_found = FALSE;

        foreach($special_ops AS $op) {
            if(strpos($search, $op) !== FALSE) {
                $prox_found = TRUE;
                break;
            }
        }

        return $prox_found;
    }

    static public function parseStrongs($search) {
        $search = static::parseSimpleQueryTerms($search);
        $strongs = [];

        foreach($search as $term) {
            if(static::isTermStrongs($term)) {
                $strongs[] = $term;
            }
        }

        return $strongs;
    }

    public function booleanizeQuery($query, $search_type, $arg3 = NULL) {
        if($search_type == 'boolean') {
            return $query;
        }

        $query  = trim( preg_replace('/\s+/', ' ', $query) );
        $parsed = static::parseSimpleQueryTerms($query);

        switch($search_type) {
            case 'strongs':
                // Do nothing, for now
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

    protected function _validateHelper($search, $search_type) {
        // Check for misplaced reference by parsing the search as a passage reference
        $Passages = Passage::parseReferences($search, $this->languages, 2);
        $allow_common_words = config('bss.search_common_words');

        if(is_array($Passages)) {
            foreach($Passages as $Passage) {
                if(!$Passage->isSingleBook() && !$Passage->hasErrors()) {
                    $this->addTransError( 'errors.invalid_search.reference', ['search' => $search], 4);
                    return FALSE;
                }
            }
        }

        if($search_type != 'regexp' && strpos($search, '"') === FALSE && strpos($search, '`') === FALSE) {
            $keywords = static::parseQueryTerms($search);

            if($search && empty($keywords)) {
                return $this->addTransError('errors.invalid_search.general', ['search' => $search], 4);
            }

            if($search_type != 'phrase' && $allow_common_words != 'always') {                
                // Check for disallowed words per language
                $has_banned = $has_allowed = false;
                $languages = $this->languages;

                if($this->options['language']) {
                    $languages[] = $this->options['language'];
                }

                $languages = array_unique($languages);
                $languages = array_filter($languages);

                $banned = [];

                foreach($languages as $lang) {
                    $Language = Models\Language::findByCode($lang);
                    
                    if($Language) {
                        $banned_words = $Language->getCommonWordsAsArray();

                        foreach($keywords as $k) {
                            $k = strtolower($k);

                            if(in_array($k, $banned_words)) {
                                $banned[] = $k;
                                $has_banned = true;
                            } else {
                                $has_allowed = true;
                            }
                        }
                    }
                }

                if($allow_common_words == 'never' && $has_banned || $allow_common_words == 'exact' && !$has_allowed && $has_banned) {
                    return $this->addTransError('errors.common_words', [
                        'wordlist' => implode(', ', array_unique($banned))
                    ], 4);
                }
            }
            
            // Check for invalid characters
            // $invalid_chars = preg_replace('/[\p{L}\(\)|!&^ "\'0-9%]+/u', '', $search); // Original
            $invalid_chars = preg_match('/[^' . static::$term_base_regexp . '|!&^ "\'0-9%()*+]/u', $search, $matches);

            if(!empty($invalid_chars)) {
                if($search_type != 'phrase') {
                    
                }

                // $this->addTransError('errors.invalid_search.general 152', ['search' => $search], 4);
                // return FALSE;
            }
        }

        switch ($search_type) {
            case 'boolean' :
            //case 'all_words':
            //case 'and':
                return $this->_validateBoolean($search);
                break;
            default:
                if(static::containsProximityOperators($search)) {
                    $this->addError( trans('errors.prox_operator_not_allowed'), 4);
                    return FALSE;
                }

                return parent::_validateHelper($search, $search_type);
        }
    }

    protected function _validateBoolean($search) {
        $valid = TRUE;
        
        if(!$this->is_special) {
            return parent::_validateBoolean($search);
        }

        if(!parent::_validateBoolean($search, TRUE)) {
            $valid = FALSE;
        }

        $prox_parsed = $this->parseProximitySearch();
        $operators =  static::parseQueryOperators($search, $terms = NULL);

        $lim = count($prox_parsed[0]) - 1;

        foreach($prox_parsed[0] as $key => $Search) {
            if(empty($Search->search) || $Search->search == '()') {
                if($key == 0) {
                    $op = array_shift($operators);
                    return $this->addError( trans('errors.operator.op_at_beginning', ['op' => $op]) );
                } elseif ($key == $lim) {
                    $op = array_pop($operators);
                    return $this->addError( trans('errors.operator.op_at_end', ['op' => $op]) );
                } else {
                    return $this->addError( trans('errors.invalid_search.general', ['search' => $search]) );
                }
            }

            if(!$Search->validate()) {
                $valid = FALSE;
                $errors = $Search->getErrors();

                foreach($errors as $key => $error) {
                    if($error == trans('errors.paren_mismatch')) {
                        $errors[$key] = trans('errors.prox_paren_mismatch');
                    }
                }

                $this->addErrors($errors, $Search->getErrorLevel());
            }
        }


        return !$this->hasErrors();
    }

    /**
     * Parses out the terms of a boolean query
     * @param string $query standardized, booleanized query
     * @return array $parsed
     */
    public static function parseQueryTerms($query, $breakdown = FALSE) {
        // Remove operators that otherwise would be interpreted as terms
        $find   = array('CHAPTER', 'CHAP', 'BOOK');
        $parsing = str_replace($find, ' ', $query);
        $parsing = preg_replace('/PROXC\([0-9]+\)/', ' ', $parsing);
        $parsing = preg_replace('/PROX\([0-9]+\)/',  ' ', $parsing);
        $parsing = preg_replace('/PROC\([0-9]+\)/',  ' ', $parsing);
        return parent::parseQueryTerms($parsing, $breakdown);
    }

    /**
     * Indicates if the given term is a Strongs number
     * @param string $term
     * @return boolean
     */
    public static function isTermStrongs($term) {
        return (bool)preg_match('/^[GHgh][0-9]+$/', $term);
    }

    public static function getTermType($term) {
        if(static::isTermStrongs($term)) {
            return 'strongs';
        }

        return parent::getTermType($term);
    }

    /**
     * Standardizes the boolean query, adds AND where implied
     * @param string $query
     * @return string
     */
    public static function standardizeBoolean($query) {
        $query = static::standardizeProximityOperators($query, '~ ');
        $query = parent::standardizeBoolean($query);

        $find  = array('~p~', '~c~', '~b~', '~l~');
        $repl  = array('PROX', 'CHAP', 'BOOK', 'PROC');
        $query = str_replace($find, $repl, $query);
        $query = str_replace('PROX (', 'PROX(', $query);
        $query = str_replace('PROC (', 'PROC(', $query);
        $query = trim(preg_replace('/\s+/', ' ', $query));

        return $query;
    }

    public static function standardizeProximityOperators($query, $suffix = '') {
        if(!$query) {
            return $query;
        }

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

    public function parseProximitySearch($disable_paren_wrap = FALSE) {
        if(!$this->is_special) {
            return FALSE;
        }

        $limit  = (isset($this->options['proximity_limit'])) ? intval($this->options['proximity_limit']) : 5;
        $search = $this->booleanizeQuery($this->search, $this->search_type, $limit);
        $terms  = static::parseQueryTerms($search);
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
            $separate = trim($separate);
            $separate = ($this->search_type == 'boolean' && !$disable_paren_wrap) ? '(' . $separate . ')' : $separate;
            $options = $this->options;

            foreach(static::$search_inputs as $input => $settings) {
                unset($options[$input]);
            }

            $options['search'] = $separate; // Why?
            $Search = static::parseSearch($separate, $options);
            $Searches[] = $Search;
            //$terms = array_merge($terms, $Search->terms);
        }

        $this->terms = $terms;
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
