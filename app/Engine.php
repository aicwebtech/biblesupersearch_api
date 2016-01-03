<?php

namespace App;

use App\User;
use App\Models\Bible;

class Engine {
    use Traits\Error;
    
    protected $Bibles = array(); // Array of Bible objects
    
    public function __construct() {
        // do something?
    }
    
    public function setBibles($modules) {
        $Bibles = Bible::whereIn('module', $modules)->get();
        
        foreach($modules as $module) {
            $this->addBible($module);
        }
    }
    
    public function addBible($module) {
        $Bible = Bible::findByModule($module);
        
        if($Bible) {
            $this->Bibles[$module] = $Bible;
        }
        else {
            $this->addError("Bible module '{$module}' not found");
        }
    }
    
    public function getBibles() {
        return $this->Bibles;
    }
 

}
