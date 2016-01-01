<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Verses\Standard As StandardVerses;

class Bible extends Model
{
    protected $Verses; // Verses model instance
	protected $guarded = ['id'];
	
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
     * Each Bible record points to an entire DB table
     */
    public function verses($force = FALSE) {
        if(!$this->Verses || $force) {
			$attributes = $this->getAttributes();
			$model_class = ucfirst($attributes['module']);
			$namespace = __NAMESPACE__ . '\Verses';
			$model_class_full = $namespace . '\\' . $model_class;
			
			if(!class_exists($model_class_full)) {
				$code = '
					namespace ' . $namespace . ';
					class ' . $model_class . ' extends Standard {
						protected $hasClass = FALSE;
					}
				';
				
                eval($code);
			}

			$this->Verses = new $model_class_full();
			$this->Verses->setBible($this); // This circular reference may be a bad thing
		}
        return $this->Verses;
    }
	
	public function install() {
		$this->verses()->install();
		$this->installed = 1;
		$this->save();
	}
	
	public function uninstall() {
		$this->verses()->uninstall();
		$this->installed = 0;
		$this->enabled = 0;
		$this->save();
	}
	
	public static function findByModule($module, $fail = FALSE) {
		if($fail) {
			return Bible::where('module', $module)->firstOrFail();
		}
		else {
			return Bible::where('module', $module)->first();
		}
	}
    
    public function setEnabledAttribute($value) {
        //var_dump($this->installed);
        //var_dump($value);
        
        /*
        if($this->installed) {
            echo('Using actual value ' . $value . PHP_EOL);
            $this->attributes['enabled'] = $value;
        }
        else {
            echo('Using 0 ' . PHP_EOL);
            $this->attributes['enabled'] = 0;
        }
        */
        
        $this->attributes['enabled'] = ($this->installed) ? $value : 0;
    }
}
