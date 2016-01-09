<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Verses\Standard As StandardVerses;

class Bible extends Model {

    protected $Verses; // Verses model instance
    protected $verses_class_name; // Name of verses class
    protected $guarded = ['id'];

    /**
     * Create a new Bible Instance
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []) {
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
        if (!$this->Verses || $force) {
            $attributes = $this->getAttributes();
            $class_name = self::getVerseClassNameByModule($this->module);
            $this->Verses = new $class_name();
            $this->Verses->setBible($this); // This circular reference may be a bad thing
        }
        return $this->Verses;
    }

    public function install() {
        if (!$this->installed) {
            $this->verses()->install();
            $this->installed = 1;
            $this->save();
        }
    }

    public function uninstall() {
        if ($this->installed) {
            $this->verses()->uninstall();
            $this->installed = 0;
            $this->enabled = 0;
            $this->save();
        }
    }

    public static function findByModule($module, $fail = FALSE) {
        if ($fail) {
            return Bible::where('module', $module)->firstOrFail();
        } 
        else {
            return Bible::where('module', $module)->first();
        }
    }
    
    /**
     * Determine the class name for the Verses model for the given module
     * @param string $module
     * @return string $class_name;
     */
    public static function getVerseClassNameByModule($module) {
        $model_class = ucfirst($module);
        $namespace = __NAMESPACE__ . '\Verses';
        $class_name = $namespace . '\\' . $model_class;

        if (!class_exists($class_name)) {
            $code = '
                namespace ' . $namespace . ';
                class ' . $model_class . ' extends Standard {
                        protected $hasClass = FALSE;
                }
            ';
            eval($code);
        }
        
        return $class_name;
    }
    
    /**
     * Determine the class name for the Verses model for the current Bible instance
     * @return string $class_name;
     */
    public function getVerseClassName() {
        return self::getVerseClassNameByModule($this->module);
    }
    
    /**
     * Enabled mutator
     * @param string $value
     */
    public function setEnabledAttribute($value) {
        $this->attributes['enabled'] = ($this->installed) ? $value : 0;
    }

    /**
     * Module mutator
     * @param string $value
     */
    public function _setModuleAttribute($value) {
        $matched = preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $value, $matches);
        $this->attributes['module'] = $value;
        self::where('1','1')->get();
    }
    
}
