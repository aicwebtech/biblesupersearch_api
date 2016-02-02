<?php

namespace App;

use App\User;
use App\Models\Bible;
use App\Passage;
use App\Search;

class Engine {
    use Traits\Error;
    
    protected $Bibles = array(); // Array of Bible objects
    protected $languages = array();
    
    public function __construct() {
        // do something?
    }
    
    public function setBibles($modules) {
        $modules = (is_array($modules)) ? $modules : array($modules);
        $Bibles = Bible::whereIn('module', $modules)->get();
        
        foreach($modules as $module) {
            $this->addBible($module);
        }
    }
    
    public function addBible($module) {
        $Bible = Bible::findByModule($module);
        $this->languages = array();
        
        if($Bible) {
            $this->Bibles[$module] = $Bible;
            
            if(!in_array($Bible->lang_short, $this->languages)) {
                $this->languages[] = $Bible->lang_short;
            }
        }
        else {
            $this->addError("Bible text '{$module}' not found");
        }
    }
    
    public function getBibles() {
        return $this->Bibles;
    }
    
    /**
     * 'Query' API Action
     * Primary Bible Look Up And Search
     * Implements the bulk of the 
     * 
     * @param array $input request data
     * @return array $results search / look up results.  
     */
    public function actionQuery($input) {
        $results = array();
        $this->setBibles($input['bible']);
        
        // Todo - Routing and merging of multiple elements here
        $references = empty($input['reference']) ? NULL : $input['reference'];
        $keywords   = empty($input['search']) ? NULL : $input['search'];
        
        $is_search = (empty($keywords)) ? FALSE : TRUE;
        $Passages = Passage::parseReferences($references, $this->languages, $is_search);
        $Search   = Search::parseSearch($keywords, $input);
        
        foreach($this->Bibles as $Bible) {
            $results[$Bible->module] = $Bible->getSearch($Passages, $Search, $input);
        }
        
        // Todo: Error handling
        // Todo: Format data structure
        
        return $results;
    }

}
