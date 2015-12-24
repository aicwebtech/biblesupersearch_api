<?php

namespace App\Models\Verses;

use Illuminate\Database\Eloquent\Model;

class Standard extends Abs
{
    protected $hasClass = TRUE; // Indicates if this instantiation has it's own coded extension of this class.
    //
    public function __construct(array $attributes = array()) {
        parent::__construct($attributes);
        
        var_dump(get_called_class());
        var_dump($this->hasClass);
    }
    
    public function classFileExists() {
        return $this->hasClass;
    }
}
