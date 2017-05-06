<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Verses\VerseStandard As StandardVerses;
use App\Passage;
use App\Search;
use Illuminate\Support\Arr;
use ZipArchive;

class Bible extends Model {

    protected $Verses; // Verses model instance
    protected $verses_class_name; // Name of verses class
    //protected $guarded = array('id'); // BAD idea!
    protected $fillable = array(
        'name',
        'shortname',
        'lang',
        'lang_short',
        'module',
        'year',
        'description',
        'copyright',
        'italics',
        'strongs',
        'rank',
    );

    // List of fields to NOT export when creating modules
    protected $do_not_export = array('id', 'created_at', 'updated_at', 'enabled', 'installed');

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

            if(class_exists($class_name)) {
                $this->Verses = new $class_name();
                $this->Verses->setBible($this); // This circular reference may be a bad thing
            }
            else {
                $this->Verses = new Verses\VerseStandard();
                $this->Verses->setModule($this->module, TRUE);
                $this->Verses->setBible($this); // This circular reference may be a bad thing
            }
        }

        return $this->Verses;
    }

    /**
     * Processes and executes the Bible search query
     *
     * @param array $Passages Array of App/Passage instances, represents the passages requested, if any
     * @param App/Search $Search App/Search instance, reporesenting the search keywords, if any
     * @param array $parameters Search parameters - user input
     * @return array $Verses array of Verses instances (found verses)
     */
    public function getSearch($Passages = NULL, $Search = NULL, $parameters = array()) {
        return $this->verses()->getSearch($Passages, $Search, $parameters);
    }

    public function getVersesByBCV($bcv) {
        return $this->verses()->getVersesByBCV($bcv);
    }

    public function install($structure_only = FALSE) {
        if (!$this->installed) {
            $this->verses()->install($structure_only);
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

    public function export($overwrite = FALSE) {
        $export_fields = static::getExportFields();
        $mode = ($overwrite) ? ZipArchive::OVERWRITE : ZipArchive::CREATE;
        $info = Arr::except($this->attributes, $this->do_not_export);
        $del  = static::getExportDelimiter();
        $info['delimiter'] = $del; // Store this in case we change it in the future
        $info['fields'] = $export_fields;
        $info = json_encode($info);
        $data = $this->verses()->exportData();
        $path = $this->getModuleFilePath();
        $eol  = PHP_EOL; //'\n';

        if(!$data) {
            return FALSE;
        }

        // Add headers - # makes it a comment
        $data_str = '';
        $data_str .= "# Bible SuperSearch Module for '{$this->name}'  (Module:{$this->module})" . $eol;
        $data_str .= '#' . $eol;
        $data_str .= '# For use with Bible SuperSearch >= 4.0' . $eol;
        $data_str .= '#' . $eol;
        $data_str .= '# http://www.BibleSuperSearch.com' . $eol;
        $data_str .= '#' . $eol;
        $data_str .= '# Separator: ' . $del . $eol;
        $data_str .= '# Columns: ' . implode($del, $export_fields) . $eol;
        $data_str .= '#' . $eol;

        foreach($data as $key => $row) {
            $rd = array();
            //$row['text'] = trim($row['text']);

            foreach($export_fields as $field) {
                $rd[] = empty($row[$field]) ? NULL : trim($row[$field]);
            }

            $data_str .= implode($del, $rd) . $eol;
        }

        $Zip = new ZipArchive();
        $res = $Zip->open($path, $mode);

        if($res === TRUE) {
            $Zip->addFromString('verses.txt', $data_str);
            $Zip->addFromString('info.json', $info);
            $Zip->close();
            return TRUE;
        }

        return FALSE;
    }

    public function getModuleFilePath() {
        return static::getModulePath() . $this->module . '.zip';
    }

    public static function getExportFields() {
        // Warning: Add new items to the end, do not change the order or existing modules will break
        return array('book', 'chapter', 'verse', 'text', 'italics', 'strongs');
    }

    public static function getExportDelimiter() {
        return '|';
    }

    public static function findByModule($module, $fail = FALSE) {
        if ($fail) {
            return Bible::where('module', $module)->firstOrFail();
        }
        else {
            return Bible::where('module', $module)->first();
        }
    }

    public static function getModulePath() {
        return dirname(__FILE__) . '/../../bibles/modules/';
    }

    public static function createFromModuleFile($module) {
        $file  = static::getModulePath() . $module . '.zip';
        $Bible = static::findByModule($module);
        $Zip   = static::openModuleFileByModule($module);

        if($Bible) {
            return FALSE;
        }

        if($Zip) {
            $json  = $Zip->getFromName('info.json');
            $attr  = json_decode($json, TRUE);
            $Bible = static::create($attr);
            $Zip->close();
            return $Bible;
        }

        return FALSE;
    }

    public static function getListOfModuleFiles() {
        $dir = static::getModulePath();
        $list = array();

        if(is_dir($dir)) {
            $list_raw = scandir($dir);

            foreach($list_raw as $item) {
                if($item == '.' || $item == '..' || $item == 'readme.txt') {
                    continue;
                }

                if(!preg_match('/\.(zip)$/i', $item)) {
                    continue;
                }

                $list[] = $item;
            }
        }

        return $list;
    }

    /* Scans the module directory
     * Adds Bible records for Bibles not existing
     */
    public static function populateBibleTable() {
        $list = static::getListOfModuleFiles();

        foreach($list as $file) {
            $module = substr($file, 0, strlen($file) - 4);
            $Bible  = static::createFromModuleFile($module);
        }
    }

    public static function openModuleFileByModule($module) {
        $file  = static::getModulePath() . $module . '.zip';
        $Zip   = new ZipArchive();

        if($Zip->open($file) === TRUE) {
            return $Zip;
        }

        return FALSE;
    }

    public function openModuleFile() {
        return static::openModuleFileByModule($this->module);
    }

    /**
     * Determine the class name for the Verses model for the given module
     * @param string $module
     * @return string $class_name;
     */
    public static function getVerseClassNameByModule($module) {
        $model_class = studly_case($module);
        $namespace = __NAMESPACE__ . '\Verses';
        $class_name = $namespace . '\\' . $model_class;

        if (!class_exists($class_name)) {
            $code = '
                namespace ' . $namespace . ';
                class ' . $model_class . ' extends VerseStandard {
                        protected $hasClass = FALSE;
                }
            ';

            eval($code); // Need this working on live server.
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

    public function getRandomReference($random_mode) {
        return $this->verses()->getRandomReference($random_mode);
    }
}
