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
        if(empty($name)) {
            return FALSE;
        }

        // Because searches are also tested against this, we must filter out items with boolean or regexp operators
        $test = preg_replace('/[\p{L}0-9 ]+/i', '', $name);

        if(!empty($test)) {
            return FALSE;
        }

        // This logic may be needed elsewhere
        $default_class_name = self::getClassNameByLanguage(env('DEFAULT_LANGUAGE_SHORT', 'en'));

        if($language) {
            $class_name = self::getClassNameByLanguage($language);
        }
        elseif(get_called_class() != get_class()) {
            $class_name = get_called_class();
        }
        else {
            $class_name = $default_class_name;
        }

        if(!class_exists($class_name)) {
            $class_name = $default_class_name;
        }

        if(!is_string($name)) {
            return $class_name::find(intval($name));
        }

        $name = trim(trim($name), '.');

        // Attempt 0: Book Number
        if(preg_match('/^[0-9]{2}[B]$/', $name)) {
            $id = intval($name);

            if($id) {
                return $class_name::find($id);
            }
        }

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

        // Attempt 4: Loose matching with REGEXP
        $matching_middle = $name;
        //$matching_end = '/ ' . $name . '';
        $Book = $class_name::where('matching1', 'REGEXP', $matching_middle)
            -> orwhere('matching2', 'REGEXP', $matching_middle)
            //-> orwhere('matching1', 'REGEXP', $matching_end)
            //-> orwhere('matching2', 'REGEXP', $matching_end)
            -> first();

        if($Book) {
            return $Book;
        }

        return $Book;
    }
}
