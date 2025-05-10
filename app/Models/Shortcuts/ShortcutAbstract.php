<?php

namespace App\Models\Shortcuts;

use Illuminate\Database\Eloquent\Model;

class ShortcutAbstract extends Model
{
    protected $language;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []) {
        if(empty($this->language)) {
            $class = explode('\\', get_called_class());
            $this->language = strtolower(array_pop($class));
        }

        if(empty($this->table)) {
            $this->table = 'shortcuts_' . $this->language;
        }

        parent::__construct($attributes);
    }

    public static function getClassNameByLanguage($language) {
        $class_name = __NAMESPACE__ . '\\' . ucfirst($language);
        return $class_name;
    }

    /**
     *
     * @param string|int $name
     */
    public static function findByEnteredName($name, $language = NULL) {
        if(empty($name)) {
            return FALSE;
        }

        // This logic may be needed elsewhere
        if($language) {
            $class_name = self::getClassNameByLanguage($language);
        }
        elseif(get_called_class() != __CLASS__) {
            $class_name = get_called_class();
        }
        else {
            $class_name = self::getClassNameByLanguage(config('bss.defaults.language_short'));
        }

        if(!is_string($name)) {
            return $class_name::find(intval($name));
        }

        $name = trim(trim($name), '.');

        // Attempt 1: Direct matching
        $SC = $class_name::where('name', $name)
                -> orwhere('short1', $name)
                -> orwhere('short2', $name)
                -> orwhere('short3', $name)
                -> first();

        if($SC) {
            return $SC;
        }

        return FALSE;
    }
}
