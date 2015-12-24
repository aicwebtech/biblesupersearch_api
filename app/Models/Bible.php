<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Verses\Standard As StandardVerses;

class Bible extends Model
{
    protected $Verses; // Verses model instance

    /**
     * Create a new Bible Instance
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        
        //print_r($attributes);
        //print_r($this);
    }
    
    /**
     * Mimic a DB relationship
     * 'One to TABLE' relationship
     * Each Bible record points to an entire table
     */
    public function verses() {
        $attributes = $this->getAttributes();
        //print_r($attributes);
        $model_class = ucfirst($attributes['module']);
        $namespace = __NAMESPACE__ . '\Verses';
        $model_class_full = $namespace . '\\' . $model_class;
        var_dump($model_class);
        
        if(!class_exists($model_class_full)) {
            $code = '
                namespace ' . $namespace . ';
                class ' . $model_class . ' extends Standard {
                    protected $hasClass = FALSE;
                }
            ';
            
            eval($code);
        }
        //$this->Verses = new Verses\Kjv;
        $this->Verses = new $model_class_full();
        var_dump(get_called_class());
        return $this->Verses;
    }
}
