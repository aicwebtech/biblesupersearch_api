<?php

namespace App\Models\Books;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Database\Seeders\DatabaseSeeder;
use App\Models\Language;

class BookAbstract extends Model
{
    protected $language;

    protected $fillable = [
        'name', 'shortname', 'matching1', 'matching2',
    ];

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []) 
    {
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
    public static function getClassNameByLanguageRaw($language) 
    {
        $class_name = __NAMESPACE__ . '\\' . studly_case(strtolower($language));
        return $class_name;
    }

    /**
     * Gets the class name for the book list model for the given language
     * If no class exists for the specified language, returns that for the default language.
     * @param string $language
     * @return string the class name
     */
    public static function getClassNameByLanguage($language, $make = true, $perm = false) 
    {
        $class_name = static::getClassNameByLanguageRaw($language);

        if(!class_exists($class_name) && $make) {
            static::makeClassByLanguage($language, $perm);
        }

        if(!class_exists($class_name)) {
            $class_name = static::getClassNameByLanguageRaw(config('app.locale'));
        }

        if(!class_exists($class_name)) {
            throw new StandardException('Cannot find book class for default language!');
        }

        return $class_name;
    }

    /**
     * Gets the class name for the book list model for the given language
     * Does NOT fall back to default language
     * @param string $language
     * @return string|bool the class name or false if class not found and not createable
     */
    public static function getClassNameByLanguageStrict($language, $make = true, $perm = false) 
    {
        $class_name = static::getClassNameByLanguageRaw($language);

        if(!class_exists($class_name) && $make) {
            static::makeClassByLanguage($language, $perm);
        }

        if(!class_exists($class_name)) {
            return false;
        }

        return $class_name;
    }

    public static function makeClassByLanguage($language)
    {
        if(!Language::validateLanguage($language)) {
            return;
        }
        
        $model_class = studly_case(strtolower($language));
        $namespace = __NAMESPACE__;
        $class_name = $namespace . '\\' . $model_class;

        if (!class_exists($class_name)) {
            $table = 'books_' . strtolower($language);
            $perm_file = (func_num_args() >= 2) ? func_get_arg(1) : FALSE;
            
            if(!Schema::hasTable($table)) {
                return;
            }

            $code = '
                // Auto-generated class
                namespace ' . $namespace . ';
                class ' . $model_class . ' extends BookAbstract 
                {
                    protected $hasClass = false;
                    protected $table = \'' . $table . '\';
                }
            ';

            if($perm_file && is_writable(dirname(__FILE__))) {
                // Create permanent class file and include it
                $code = '
                    // Auto-generated class
                    namespace ' . $namespace . ';
                    class ' . $model_class . ' extends BookAbstract 
                    {
                        protected $table = \'' . $table . '\';
                    }
                ';

                $filepath = dirname(__FILE__) . '/' . $model_class . '.php';
                file_put_contents($filepath, '<?php ' . $code);
                include($filepath);
            }
            else if(is_writable(sys_get_temp_dir())) {
                // Create temp class file, include it, then delete it
                $tempfile = tempnam(sys_get_temp_dir(), $model_class . '.php');
                file_put_contents($tempfile, '<?php ' . $code);
                include($tempfile);
                unlink($tempfile);
            }
            else {
                // Fallback to eval
                eval($code); // Need this working on live server.
            }
        }
    }

    public static function getLanguage() 
    {
        return strtolower(get_called_class());
    }

    /**
     *
     * @param string|int $name
     */
    public static function findByEnteredName($name, $language = NULL, $multiple = FALSE, $loose = false) 
    {
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
        elseif(get_called_class() != __CLASS__) {
            $class_name = get_called_class();
        }
        else {
            $class_name = $default_class_name;
            $language = config('bss.defaults.language_short');
        }

        if(!class_exists($class_name)) {
            $class_name = $default_class_name;
        }

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

        // Attempt 1: Direct, exact matching
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

        if($Book || !$loose) {
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

    public static function createTableAndMigrateFromCsv($language = null)
    {
        $language = $language ?: static::getLanguage();
        $lang_lc = strtolower($language);
        $tn = 'books_' . $lang_lc;
        $csv_file = 'bible_books/' . $lang_lc . '.csv';

        // read in all CSV
        $map = ['id', 'name', 'shortname', 'matching1', 'matching2'];

        if(!\App\Importers\Database::importFileExists($csv_file)) {
            return false;
        }

        if(!static::createBookTable($language)) {
            return true; // This has been successful previously
        }

        Model::unguard();
        \App\Importers\Database::importCSV($csv_file, $map, static::getClassNameByLanguage($language));
        Model::reguard();

        DatabaseSeeder::setCreatedUpdated($tn);
        return true;
    }

    public static function migrateFromCsv($language = null)
    {
        $language = $language ?: static::getLanguage();
        $lang_lc = strtolower($language);
        $tn = 'books_' . $lang_lc;
        $csv_file = 'bible_books/' . $lang_lc . '.csv';

        // read in all CSV
        $map = ['id', 'name', 'shortname', 'matching1', 'matching2'];

        if(!\App\Importers\Database::importFileExists($csv_file)) {
            return false;
        }

        Model::unguard();
        \App\Importers\Database::importCSV($csv_file, $map, static::getClassNameByLanguage($language));
        Model::reguard();

        DatabaseSeeder::setCreatedUpdated($tn);
        return true;
    }

    public static function exportToCsv($language = null)
    {
        $csv_file = static::getCsvFileName($language);
        $map = ['id', 'name', 'shortname', 'matching1', 'matching2'];

        \App\Importers\Database::exportCSV($csv_file, $map, static::getClassNameByLanguage($language));
    }

    public static function getCsvFileName($language = null)
    {   
        $language = $language ?: static::getLanguage();
        return 'bible_books/' . strtolower($language) . '.csv';
    }

    /* OBSOLETE */
    public static function createBookTables() 
    {
        $languages = static::getSupportedLanguages();
        
        foreach($languages as $lang) {
            if(!static::createBookTable($lang)) {
                continue;
            }

            $lang_lc = strtolower($lang);
            $tn = 'books_' . $lang_lc;
            $csv_file = 'bible_books/' . $lang_lc . '.csv';
            $sql_file = 'bible_books_' . $lang_lc . '.sql';

            static::migrateFromCsv($lang);
        }
    }

    public static function createBookTable($language)
    {
        $lang_lc = strtolower($language);
        $tn = 'books_' . $lang_lc;

        if(Schema::hasTable($tn)) {
            return false;
        }

        Schema::create($tn, function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('shortname')->nullable();
            $table->string('matching1')->nullable();
            $table->string('matching2')->nullable();
            $table->timestamps();
        });

        return true;
    }

    /* SEMI-OBSOLETE */
    // still used to clean up book lists when uninistalling
    // but this needs to change!
    public static function dropBookTables()
    {
        $languages = static::getSupportedLanguages();

        foreach($languages as $lang) {
            $tn = 'books_' . strtolower($lang);
            Schema::dropifExists($tn);
        }
    }    

    public static function dropBookTable($language)
    {
        $tn = 'books_' . strtolower($language);
        Schema::dropifExists($tn);
    }

    static public function getSupportedLanguages() 
    {
        return [
            // Languages supported prior to v 5.6
            'ar', 'de', 'en', 'es', 'fr', 'hu', 'it', 'nl', 'ro', 'ru', 'zh', 'hi', 'pt', 'ja', 'zh_CN', 'zh_TW',

            // Languages with book lists (and UI translations) added in v5.6
            // 'id', 'sw', 'vi', 'ko', 'tl', 'pl', 'fa', 'tr', 'sq', 'th', 'he', 'mi', 'af', 'cs', 'lt',

            // Language support completely added in v5.6
            'gu',
        ];
    }

    static public function isSupportedLanguage($lang_code) 
    {
        return \App\Models\Language::hasBookSupport($lang_code);
    }
}
