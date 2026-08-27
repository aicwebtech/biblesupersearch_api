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
    protected static $accent_folding_map = null;

    protected $fillable = [
        'name', 'shortname', 'matching1', 'matching2',
    ];

    protected static $cache_by_language_and_id = [];

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
        $language = $language ?: config('bss.defaults.language_short');
        
        $class_name = $language ? __NAMESPACE__ . '\\' . studly_case(strtolower($language)) : null;
        return $class_name;
    }

    public static function getEffectiveClassName($language = null)
    {
        if($language) {
            return static::getClassNameByLanguage($language);
        }

        if(get_called_class() != __CLASS__) {
            return get_called_class();
        }

        return static::getClassNameByLanguage(config('bss.defaults.language_short'));
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

            // Defense in depth: never materialize a class definition built from
            // anything that is not a strict identifier / table name, regardless of
            // upstream validation. This guarantees the generated source is safe
            // even if a future caller reaches this method without validateLanguage().
            if(!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $model_class) || !preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
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
                // Intentionally no dynamic-code execution fallback here. If no
                // writable location is available to materialize the class, fail loudly.
                throw new \RuntimeException(sprintf(
                    'Unable to generate book model class "%s": no writable directory available (checked: %s, %s)',
                    $model_class,
                    dirname(__FILE__),
                    sys_get_temp_dir()
                ));
            }
        }
    }

    public static function getLanguage() 
    {
        return strtolower(get_called_class());
    }

    public static function isValidBookId(int|string $id): bool
    {
        return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 66]]) !== false;
    }

    public static function findByIdAndLanguage($id, $language = NULL) 
    {
        $id = (int)$id;
        
        if(empty($id)) {
            return FALSE;
        }

        $language = $language ?: config('bss.defaults.language_short');

        if(!isset(self::$cache_by_language_and_id[$language])) {
            self::$cache_by_language_and_id[$language] = [];
        }

        if(!isset(self::$cache_by_language_and_id[$language][$id])) {
            $class_name = self::getEffectiveClassName($language);
            
            self::$cache_by_language_and_id[$language][$id] = $class_name::find($id);
        }

        return self::$cache_by_language_and_id[$language][$id];
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

        // When special chars are present, only allow exact matching (not fuzzy) to prevent search injection.
        // Book names in some languages legitimately contain parentheses (e.g. Latvian, Lithuanian).
        $has_special_chars = (bool) $test;

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
        if(preg_match('/^[0-9]{1,2}[B]$/', $name) || is_int($name)) {
            $id = (int)$name;

            if($id) {
                $Book = self::findByIdAndLanguage($id, $language);
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

        // Attempt 1b: Collation-independent exact matching.
        // The query above relies on the database collation to be case- and accent-insensitive.
        // Many environments use stricter collations, which causes foreign book names to be
        // missed (e.g. Russian "Бытие" != "бытие", Spanish "Génesis"), silently falling back
        // to the English book list. Re-check exact matches in PHP so book lookups behave the
        // same regardless of the database's collation.
        $Book = self::findByNormalizedName($class_name, $name, $multiple);

        if($multiple ? !empty($Book) : $Book) {
            return $Book;
        }

        if($has_special_chars) {
            return ($multiple) ? [] : NULL;
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
        $Query = $class_name::where('matching1', 'REGEXP', $matching_middle)
            -> orwhere('matching2', 'REGEXP', $matching_middle);

        $Book = ($multiple) ? $Query->get()->all() : $Query->first();

        if($Book) {
            return $Book;
        }

        return $Book;
    }

    /**
     * Cache of all book rows with their pre-computed normalized field values, keyed by
     * book list class name. Each entry is `['Book' => static, 'normalized' => string[]]`.
     *
     * @var array<string, array<int, array{Book: static, normalized: array<int, string>}>>
     */
    protected static $all_books_cache = [];

    /**
     * Finds a book by performing a case- and accent-insensitive exact match in PHP,
     * independent of the database collation.
     *
     * @param  string  $class_name
     * @param  string  $name
     * @param  bool  $multiple
     * @return static|array<int, static>|null
     */
    protected static function findByNormalizedName(string $class_name, string $name, bool $multiple = FALSE)
    {
        $needle = self::normalizeForMatch($name);

        if($needle === '') {
            return ($multiple) ? [] : NULL;
        }

        $matches = [];

        foreach(self::getNormalizedBooks($class_name) as $entry) {
            if(in_array($needle, $entry['normalized'], true)) {
                $matches[] = $entry['Book'];

                if(!$multiple) {
                    return $matches[0];
                }
            }
        }

        return ($multiple) ? $matches : NULL;
    }

    /**
     * Returns all books for the given class with their field values pre-normalized once,
     * so repeated lookups do not re-normalize every field of every book on each miss.
     *
     * @param  string  $class_name
     * @return array<int, array{Book: static, normalized: array<int, string>}>
     */
    protected static function getNormalizedBooks(string $class_name): array
    {
        if(!isset(self::$all_books_cache[$class_name])) {
            $cache = [];

            foreach($class_name::all()->all() as $Book) {
                $normalized = [];

                foreach(['name', 'shortname', 'matching1', 'matching2'] as $field) {
                    $normalized[] = self::normalizeForMatch($Book->{$field});
                }

                $cache[] = ['Book' => $Book, 'normalized' => $normalized];
            }

            self::$all_books_cache[$class_name] = $cache;
        }

        return self::$all_books_cache[$class_name];
    }

    /**
     * Normalizes a book name for collation-independent comparison by lower-casing and
     * folding common Latin diacritics so values like "Génesis" and "genesis" are equal.
     *
     * @param  string|null  $string
     * @return string
     */
    protected static function normalizeForMatch($string): string
    {
        if($string === null) {
            return '';
        }

        $string = trim((string) $string);

        if($string === '') {
            return '';
        }

        $string = function_exists('mb_strtolower') ? mb_strtolower($string, 'UTF-8') : strtolower($string);
        $string = strtr($string, self::accentFoldingMap());

        // preg_replace with the /u flag returns null on malformed UTF-8 (which can survive
        // when mb_strtolower is unavailable to sanitize it); fall back to a byte-wise
        // whitespace collapse so matching degrades gracefully instead of returning null.
        $collapsed = preg_replace('/\s+/u', ' ', $string);

        return ($collapsed === null) ? preg_replace('/\s+/', ' ', $string) : $collapsed;
    }

    /**
     * Map of lower-case accented Latin characters to their base form, used for
     * accent-insensitive matching when the intl Normalizer extension is unavailable.
     *
     * @return array<string, string>
     */
    protected static function accentFoldingMap(): array
    {
        if (self::$accent_folding_map === null) {
            self::$accent_folding_map = [
                'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a','ā'=>'a','ă'=>'a','ą'=>'a',
                'ç'=>'c','ć'=>'c','č'=>'c','ĉ'=>'c','ċ'=>'c',
                'ð'=>'d','ď'=>'d','đ'=>'d',
                'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e','ē'=>'e','ĕ'=>'e','ė'=>'e','ę'=>'e','ě'=>'e',
                'ĝ'=>'g','ğ'=>'g','ġ'=>'g','ģ'=>'g',
                'ĥ'=>'h','ħ'=>'h',
                'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i','ī'=>'i','ĭ'=>'i','į'=>'i','ı'=>'i',
                'ĵ'=>'j',
                'ķ'=>'k',
                'ĺ'=>'l','ļ'=>'l','ľ'=>'l','ł'=>'l',
                'ñ'=>'n','ń'=>'n','ņ'=>'n','ň'=>'n',
                'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','ø'=>'o','ō'=>'o','ŏ'=>'o','ő'=>'o',
                'ŕ'=>'r','ŗ'=>'r','ř'=>'r',
                'ś'=>'s','ŝ'=>'s','ş'=>'s','š'=>'s',
                'ţ'=>'t','ť'=>'t','ŧ'=>'t',
                'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u','ū'=>'u','ŭ'=>'u','ů'=>'u','ű'=>'u','ų'=>'u',
                'ŵ'=>'w',
                'ý'=>'y','ÿ'=>'y','ŷ'=>'y',
                'ź'=>'z','ż'=>'z','ž'=>'z',
                'þ'=>'th','ß'=>'ss','æ'=>'ae','œ'=>'oe',
            ];
        }

        return self::$accent_folding_map;
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

        // The generated class needs its table to exist, so it can only be resolved now. The
        // strict lookup is required here: getClassNameByLanguage() falls back to the default
        // language's model, which would import this CSV straight into books_en (harmless only
        // because the insert ignores existing ids) and leave the table just created empty.
        $class_name = static::getClassNameByLanguageStrict($language);

        if(!$class_name) {
            Schema::dropIfExists($tn);
            return false;
        }

        try {
            Model::unguard();
            \App\Importers\Database::importCSV($csv_file, $map, $class_name);
        }
        finally {
            // An import that throws must not leave Eloquent globally unguarded for the rest of
            // the process - app:install-testing catches one language's failure and carries on
            // importing the next 48.
            Model::reguard();
        }

        self::clearAllBooksCache($language);
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

        // Strict, for the same reason as createTableAndMigrateFromCsv(): a language that cannot
        // resolve its own model must not have its CSV imported into the default language's table.
        $class_name = static::getClassNameByLanguageStrict($language);

        if(!$class_name) {
            return false;
        }

        try {
            Model::unguard();
            \App\Importers\Database::importCSV($csv_file, $map, $class_name);
        }
        finally {
            // See createTableAndMigrateFromCsv().
            Model::reguard();
        }

        self::clearAllBooksCache($language);
        DatabaseSeeder::setCreatedUpdated($tn);
        return true;
    }

    public static function clearAllBooksCache($language = null)
    {
        if($language) {
            $class_name = self::getClassNameByLanguage($language);
        }
        elseif(get_called_class() != __CLASS__) {
            $class_name = get_called_class();
        }
        else {
            $class_name = static::getClassNameByLanguage(config('bss.defaults.language_short'));
        }

        unset(self::$all_books_cache[$class_name]);
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
