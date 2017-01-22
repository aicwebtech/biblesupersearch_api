<?php

namespace App\Importers;

use App\Models\Bible;
use PhpSpec\Exception\Exception;

/**
 *
 */
abstract class ImporterAbstract {
    use \App\Traits\Error;
    
    protected $bible_attributes = array();
    protected $default_dir;
    protected $file;
    protected $module;
    protected $overwrite = FALSE;
    
    protected $required = ['module', 'lang_short']; // Array of required fields (for specific importer type);
    
    public function __construct() {
        
    }
    
    abstract public function import();
    
    public function setBibleAttributes($att) {
        $this->bible_attributes = $att;
    }
    
    public function setFile($file) {
        $this->file = $file;
    }
    
    public function setProperties($file, $module, $overwrite, $attributes) {
        $this->file      = $file;
        $this->module    = $module;
        $this->overwrite = ($overwrite) ? TRUE : FALSE;
        
        $attributes['module']       = $module;
        $attributes['shortname']    = (!empty($attributes['shortname']))    ? $attributes['shortname'] : $module;
        //$attributes['name']         = (!empty($attributes['name']))         ? $attributes['name'] : $attributes['shortname'];
        $attributes['lang']         = (!empty($attributes['lang']))         ? $attributes['lang'] : NULL;
        $attributes['lang_short']   = (!empty($attributes['lang_short']))   ? $attributes['lang_short'] : NULL;
        
        foreach($this->required as $item) {
            if(empty($attributes[$item])) {
                $this->addError($item . ' is required', 4);
            }
        }
        
        $attributes['rank'] = 9999;
        $this->bible_attributes = $attributes;
        return ($this->has_errors) ? FALSE : TRUE;
    }
}
