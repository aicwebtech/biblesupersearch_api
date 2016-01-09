<?php

namespace App\Models\Books;

use Illuminate\Database\Eloquent\Model;

class BookAbstract extends Model
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
            $this->table = 'books_' . $this->language;
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
        // This logic may be needed elsewhere
        if($language) {
            $class_name = self::getClassNameByLanguage($language);
        }
        elseif(get_called_class() != get_class()) {
            $class_name = get_called_class();
        }
        else {
            $class_name = self::getClassNameByLanguage(env('DEFAULT_LANGUAGE_SHORT', 'en'));
        }
        
        $name = trim(trim($name), '.');
        
        // Attempt 1: Direct matching
        $Book = $class_name::where('name', $name)
                -> orwhere('shortname', $name)
                -> orwhere('matching1', $name)
                -> orwhere('matching2', $name)
                -> first();
        
        if($Book) {
            return $Book;
        }
        
        // Attempt 2: Begins with matching
        $matching = $name . '%';
        $Book = $class_name::where('name', 'LIKE', $matching)
            -> orwhere('shortname', 'LIKE', $matching)
            -> orwhere('matching1', 'LIKE', $matching)
            -> orwhere('matching2', 'LIKE', $matching)
            -> first();
        
        if($Book) {
            return $Book;
        }
        
        // Attempt 3: Loose matching
        $matching_middle = '% '. $name . ' %';
        $matching_end = '% ' . $name;
        $Book = $class_name::where('matching1', 'LIKE', $matching_middle)
            -> orwhere('matching2', 'LIKE', $matching_middle)
            -> orwhere('matching1', 'LIKE', $matching_end)
            -> orwhere('matching2', 'LIKE', $matching_end)
            -> first();
        
        if($Book) {
            return $Book;
        }

        // Attempt 4: Get by ID
        $Book = $class_name::find(intval($name));
        
        return $Book;
    }
}
