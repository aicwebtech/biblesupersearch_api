<?php

namespace App\Models\Verses;

use Illuminate\Database\Eloquent\Model;
use App\Models\Bible;

/**
 * Class VerseAbstract
 *
 * Base model class for the verses tables for all Bibles in the system
 * Each Bible record in the Bibles table will have an extension of this class, which tells
 *      it where (usually what table) to find it's verses
 * 
 * Used for executing searches against the Bible's verses
 *
 * Verses models should only be instantiated from within a Bible model instance
 * Abstraction allows for the potential for Bibles other than the 'standard' format
 * However, actual support for non-standard formats won't be implemented any time soon.
 */

abstract class VerseAbstract extends Model 
{

    protected $Bible;
    protected $module; // Module name
    protected $hasClass = TRUE; // Indicates if this instantiation has it's own coded extension of this class.
    public $timestamps = FALSE;

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
    */
    public function __construct(array $attributes = []) 
    {
        if (empty($this->module)) {
            $class = explode('\\', get_called_class());
            $base_class   = array_pop($class);
            $module_snake = preg_replace('/([0-9]+)/', '_$1', $base_class);
            $module_snake = snake_case($module_snake);
            $this->module = $module_snake;
        }

        $this->table = ($this->table) ? $this->table : self::getTableByModule($this->module);
        parent::__construct($attributes);
    }

    public function setBible(Bible &$Bible) 
    {
        $this->Bible = $Bible;
    }

    public function setModule($module, $set_table = FALSE) {
        $this->module = $module;

        if($set_table) {
            $this->table = self::getTableByModule($this->module);
        }
    }

    public function classFileExists() 
    {
        return $this->hasClass;
    }

    abstract public function install();
    abstract public function uninstall();
    abstract public function exportData(); // Exports ALL data from Bible table

    /**
     *
     * @param array $Passages Array of App/Passage instances, represents the passages requested, if any
     * @param App/Search $Search App/Search instance, reporesenting the search keywords, if any
     * @param array $parameters Search parameters - user input
     * @return array $Verses array of Verses instances (found verses)
     */
    public static function getSearch($Passages = NULL, $Search = NULL, $parameters = []) 
    {
        throw new StandardException('Must implement getSearch in child class!');
    }

    /**
     * Fetches verses by BCV
     * $bcv = $book * 1000000 + $chapter * 1000 + $verse
     * @param array|int $bcv
     * @return array $Verses array of Verses instances (found verses)
     */
    abstract public function getVersesByBCV($bcv);

    /**
     * Fetches verse count (MAX) by book and chapter
     * @param bool $verbose
     * @return array veres count grouped by book and chapter
     */
    abstract public function getChapterVerseCount($verbose = false);

    /**
     * Gets the table name by the module
     * @param string $module
     * @return string $table_name
     */
    public static function getTableByModule($module) 
    {
        return 'verses_' . $module;
    }

    public function getRandomReferences($random_mode) 
    {
        return FALSE;
    }
}
