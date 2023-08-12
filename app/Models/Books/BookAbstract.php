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

    /**
     * Gets the class name for the book list model for the given language
     * Warning:  This does NOT verify that the class exists.
     * @param string $language
     * @return string the class name
     */
    public static function getClassNameByLanguageRaw($language) {
        $class_name = __NAMESPACE__ . '\\' . studly_case(strtolower($language));
        return $class_name;
    }

    /**
     * Gets the class name for the book list model for the given language
     * If no class exists for the specified language, returns that for the default language.
     * @param string $language
     * @return string the class name
     */
    public static function getClassNameByLanguage($language) {
        $class_name = static::getClassNameByLanguageRaw($language);


        if(!class_exists($class_name)) {
            $class_name = static::getClassNameByLanguageRaw(config('app.locale'));
        }

        if(!class_exists($class_name)) {
            throw new StandardException('Cannot find book class for default language!');
        }

        return $class_name;
    }

    public static function getLanguage() {
        return strtolower(get_called_class());
    }

    /**
     *
     * @param string|int $name
     */
    public static function findByEnteredName($name, $language = NULL, $multiple = FALSE) {
        if(empty($name)) {
            return FALSE;
        }

        // Because searches are also tested against this, we must filter out items with boolean or regexp operators
        // Need this to work with Unicode book names such as Ésaïe (French for Isaiah)
        // Cannot remove this test as it's needed for tests / ect - removing will cause breakage!
        $test = preg_match('/[\p{Ps}\p{Pe}\(\)\\\|\+&\*]/', $name, $matches);
//        $test2 = preg_match('/".*"/', $name, $matches); // Additional test, if needed
//        $test3 = preg_match("/'.*'/", $name, $matches); // Additional test, if needed

//        $test = preg_replace('/[\p{L}0-9 ]+/i', '', $name); // Orignal 'working' test
//        $test = preg_replace('/[\p{L}\p{M}\p{N}\p{P}\p{Pf}\p{Pd}\p{Zs}]+/', '', $name); // Attempted test, not working

        if(!empty($test)) {
            return FALSE;
        }

        // This logic may be needed elsewhere
        $default_class_name = self::getClassNameByLanguage(config('bss.defaults.language_short'));

        if($language) {
            $class_name = self::getClassNameByLanguage($language);
        }
        elseif(get_called_class() != get_class()) {
            $class_name = get_called_class();
        }
        else {
            $class_name = $default_class_name;
            $language = config('bss.defaults.language_short');
        }

        if(!class_exists($class_name)) {
            $class_name = $default_class_name;
        }

        // var_dump($language);
        // var_dump($class_name);

        if(!is_string($name)) {
            return $class_name::find(intval($name));
        }

        $name = trim(trim($name), '.');

        // Attempt 0: Book Number
        if(preg_match('/^[0-9]{1,2}[B]$/', $name)) {
            $id = intval($name);

            if($id) {
                $Book = $class_name::find($id);
                return ($multiple) ? [$Book] : $Book;
            }
        }

        // Attempt 1: Direct matching
        $Query = $class_name::where('name', $name)
                -> orwhere('shortname', $name)
                -> orwhere('matching1', $name)
                -> orwhere('matching2', $name);

        $Book = ($multiple) ? $Query->get()->all() : $Query->first();

        if($Book) {
            return $Book;
        }

        if(\App\Helpers::isCommonWord($name, $language)) {
            return NULL;
        }

        // Attempt 2: Begins with matching
        $matching = $name . '%';
        $Query = $class_name::where('name', 'LIKE', $matching)
            -> orwhere('shortname', 'LIKE', $matching)
            -> orwhere('matching1', 'LIKE', $matching)
            -> orwhere('matching2', 'LIKE', $matching);

        $Book = ($multiple) ? $Query->get()->all() : $Query->first();

        if($Book) {
            return $Book;
        }

        // Attempt 3: Loose matching
        $matching_middle = '% '. $name . ' %';
        $matching_end = '% ' . $name;
        $Query = $class_name::where('matching1', 'LIKE', $matching_middle)
            -> orwhere('matching2', 'LIKE', $matching_middle)
            -> orwhere('matching1', 'LIKE', $matching_end)
            -> orwhere('matching2', 'LIKE', $matching_end);

        $Book = ($multiple) ? $Query->get()->all() : $Query->first();

        if($Book) {
            return $Book;
        }

        // Attempt 4: Loose matching with REGEXP
        $matching_middle = $name;
        //$matching_end = '/ ' . $name . '';
        $Query = $class_name::where('matching1', 'REGEXP', $matching_middle)
            -> orwhere('matching2', 'REGEXP', $matching_middle);
            //-> orwhere('matching1', 'REGEXP', $matching_end)
            //-> orwhere('matching2', 'REGEXP', $matching_end)

        $Book = ($multiple) ? $Query->get()->all() : $Query->first();

        if($Book) {
            return $Book;
        }

        return $Book;
    }

    static public function getSupportedLanguages() {
        return ['ar', 'de', 'en', 'es', 'fr', 'hu', 'it', 'nl', 'ro', 'ru', 'zh', 'hi', 'pt', 'ja', 'zh_CN', 'zh_TW'];
    }

    static public function isSupportedLanguage($lang_code) {
        return in_array($lang_code, static::getSupportedLanguages());
    }
}
