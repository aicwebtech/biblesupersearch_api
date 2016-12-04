<?php

namespace App\Importers;

use App\Models\Bible;

/**
 *
 */
abstract class ImporterAbstract {
    //use \App\Traits\Error;
    
    protected $bible_attributes = array();
    protected $default_dir;
    protected $file;
    protected $module;
    
    public function __construct() {
        
    }
    
    abstract public function import();
    
    public function setBibleAttributes($att) {
        $this->bible_attributes = $att;
    }
    
    public function setFile($file) {
        $this->file = $file;
    }
    
    public function setProperties($file, $module, $attributes) {
        $this->file   = $file;
        $this->module = $module;
        
        $attributes['module']    = $module;
        $attributes['shortname'] = (!empty($attributes['shortname'])) ? $attributes['shortname'] : $module;
        $attributes['name']      = (!empty($attributes['name']))      ? $attributes['name'] : $attributes['shortname'];
        
        $this->bible_attributes = $attributes;
    }
}
