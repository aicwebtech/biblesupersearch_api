<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Verses\VerseStandard As StandardVerses;
use App\Passage;
use App\Search;
use Illuminate\Support\Arr;
use ZipArchive;
use App\Traits\Error;

class Bible extends Model {
    use Error;

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
        'copyright_id',
        'copyright_statement',
        'url',
        'italics',
        'strongs',
        'red_letter',
        'rank',
        'official',
        'research',
        'publisher',
        'hebrew_text_id',
        'greek_text_id',
        'translation_type_id',
        'owner',
        'copyright_id',
        'citation_limit',
        'restrict',
        'module_v2',
        'importer',
        'import_file',
    );

    protected $attributes = [
        'copyright_id' => NULL,
    ];

    // List of fields to NOT export when creating modules
    protected $do_not_export = array('id', 'created_at', 'updated_at', 'enabled', 'installed');

    public $migrate_code = 0;  // 0 = no change, 1 = deleted unnessessary file, 2 = moved file, 3 = file does not exist

    /**
     * Create a new Bible Instance
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []) {
        parent::__construct($attributes);
    }

    public function language() {
        return $this->hasOne('App\Models\Language', 'code', 'lang_short');
    }

    /**
     * Mimic a DB relationship
     * 'One to TABLE' relationship
     * Each Bible record points to an entire DB table
     */
    public function verses($force = FALSE) {
        if(!$this->module) {
            throw new \Exception('Module required on Bible model to access verses model');
        }

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

    public function install($structure_only = FALSE, $enable = FALSE) {
        if (!$this->installed) {
            $success = $this->verses()->install($structure_only);

            if(!$success) {
                $this->addError('Could not install Bible table', 4);
            }
            else {
                $this->installed = 1;

                if($enable) {
                    $this->enabled = 1;
                }

                $this->save();
            }
        }
        else {
            $this->addError('Already installed', 1);
        }
    }

    public function uninstall() {
        if ($this->installed) {
            $this->verses()->uninstall();
            $this->installed = 0;
            $this->enabled = 0;
            $this->save();
        }
        else {
            $this->addError('Already uninstalled', 1);
        }
    }

    public function export($overwrite = FALSE) {
        $path = $this->getModuleFilePath();

        if(!$overwrite && is_file($path)) {
            $this->addError('Cannot export, file already exists', 4);
            return FALSE;
        }

        if(is_file($path) && !is_writable($path)) {
            return $this->addError('Cannot write file: ' . $this->getModuleFilePathShort() . ' as user ' . exec('whoami'), 4);
        }

        $export_fields = static::getExportFields();
        $mode = ($overwrite) ? ZipArchive::OVERWRITE : ZipArchive::CREATE;
        $info = Arr::except($this->attributes, $this->do_not_export);
        $del  = static::getExportDelimiter();
        $info['delimiter'] = $del; // Store this in case we change it in the future
        $info['fields'] = $export_fields;
        $info = json_encode($info);
        $ini_memory_limit = ini_get('memory_limit');
        ini_set('memory_limit', 536870912);

        $data = $this->verses()->exportData();
        $eol  = PHP_EOL; //'\n';

        if(!$data) {
            return $this->addError('No Data');
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
        }

        if($res !== TRUE) {
            $this->addError('Could not open ZIP file ' . $res);
        }

        return ($res === TRUE) ? TRUE : FALSE;
    }

    protected function _getExportInfo() {
        $info = Arr::except($this->attributes, $this->do_not_export);
        $info['delimiter'] = static::getExportDelimiter(); // Store this in case we change it in the future
        $info['fields']    = static::getExportFields();
        $info = json_encode($info);
        return $info;
    }

    public function isDownloadable() {
        if($this->restrict || !$this->copyright_id) {
            return FALSE;
        }

        if(!$this->copyrightInfo || !$this->copyrightInfo->download) {
            return FALSE;
        }

        return TRUE;
    }

    public function getCopyrightStatement() {
        if($this->copyright_statement) {
            return $this->copyright_statement;
        }

        if($this->copyright_id) {
            return $this->copyrightInfo->getProcessedCopyrightStatement();
        }

        return $this->description;
    }

    public function copyrightInfo() {
        return $this->hasOne('App\Models\Copyright', 'id', 'copyright_id');
    }

    public function updateMetaInfo($create_if_needed = FALSE) {
        $path = $this->getModuleFilePath();

        if(!$create_if_needed && !is_file($path)) {
            return $this->addError('Cannot update info, file does not exist', 4);
        }

        if($create_if_needed && !is_file($path)) {
            $this->export();
        }

        if(!is_writable($path)) {
            return $this->addError('Cannot write file: ' . $this->getModuleFilePathShort() . ' as user ' . exec('whoami'), 4);
        }

        $info = $this->_getExportInfo();
        $Zip  = new ZipArchive();
        $res  = $Zip->open($path);

        if($res === TRUE) {
            $info_old = $Zip->getFromName('info.json');

            if($info_old != $info) {
                $Zip->addFromString('info.json', $info);
            }
            else {
                $this->addError('no changed needed');
            }

            $Zip->close();
        }

        return ($res === TRUE) ? TRUE : FALSE;
    }

    public function migrateModuleFile($dry_run = FALSE) {
        $path_of = static::getModulePath();
        $path_un = static::getUnofficialModulePath();

        $path_correct = ($this->official) ? $path_of : $path_un;
        $path_wrong   = ($this->official) ? $path_un : $path_of;

        $file_path_correct = $path_correct . $this->getModuleFileName();
        $file_path_wrong   = $path_wrong   . $this->getModuleFileName();

        if(is_file($file_path_correct) && !is_file($file_path_wrong)) {
            $this->migrate_code = 0; // no changes
            return TRUE;
        }
        elseif(is_file($file_path_correct) && is_file($file_path_wrong)) {
            $this->migrate_code = 1;// deleted unneeded file

            if(!$dry_run) {
                return unlink($file_path_wrong);
            }

            return TRUE;
        }
        elseif(!is_file($file_path_correct) && is_file($file_path_wrong)) {
            $this->migrate_code = 2; // moved file

            if(!$dry_run) {
                return rename($file_path_wrong, $file_path_correct);
            }

            return TRUE;
        }
        elseif(!is_file($file_path_correct) && !is_file($file_path_wrong)) {
            $this->migrate_code = 3; // no module files
            return TRUE;
        }
    }

    public function getModuleFilePath($short = FALSE) {
        $path = ($this->official) ? static::getModulePath($short) : static::getUnofficialModulePath($short);
        return $path . $this->getModuleFileName();
    }

    public function getModuleFilePathShort() {
        return $this->getModulePath(TRUE);
    }

    public function getModuleFileName() {
        return $this->module . '.zip';
    }

    public function hasModuleFile() {
        return is_file($this->getModuleFilePath());
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

    public static function isEnabled($module) {
        $Bible = static::findByModule($module);
        return ($Bible && $Bible->enabled) ? TRUE : FALSE;
    }

    public static function getModulePath($short = FALSE) {
        return static::_getModulePathBase($short) . 'modules/';
    }

    public static function getModulePathShort() {
        return static::_getModulePathBase(TRUE) . 'modules/';
    }

    public static function getUnofficialModulePath($short = FALSE) {
        return static::_getModulePathBase($short) . 'unofficial/';
    }

    public static function getUnofficialModulePathShort() {
        return static::_getModulePathBase(TRUE) . 'unofficial/';
    }

    protected static function _getModulePathBase($short = FALSE) {
        return $short ? 'bibles/' : dirname(__FILE__) . '/../../bibles/';
    }

    public static function createFromModuleFile($module) {
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

    public static function updateFromModuleFile($module, $fields = []) {
        $Bible = static::findByModule($module);
        $Zip   = static::openModuleFileByModule($module);

        if(!$Bible) {
            return static::createFromModuleFile($module);
        }

        if($Zip) {
            $json  = $Zip->getFromName('info.json');
            $attr  = json_decode($json, TRUE);

            if(is_array($fields) && !empty($fields)) {
                $attr = Arr::only($attr, $fields);
            }

            $Bible->fill($attr);
            $Bible->save();
            $Zip->close();
            return $Bible;
        }

        return FALSE;
    }

    public static function getListOfModuleFiles() {
        $dirs = [];

        $dirs[] = static::getModulePath();
        $dirs[] = static::getUnofficialModulePath();
        $list = array();

        foreach($dirs as $dir) {
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
        }

        return $list;
    }

    /**
     * Scans the module directory
     * Adds Bible records for Bibles not existing
     */
    public static function populateBibleTable() {
        $list = static::getListOfModuleFiles();

        foreach($list as $file) {
            $module = substr($file, 0, strlen($file) - 4);
            $Bible  = static::createFromModuleFile($module);
        }
    }

    public static function updateBibleTable($fields = []) {
        $list = static::getListOfModuleFiles();

        foreach($list as $file) {
            $module = substr($file, 0, strlen($file) - 4);
            $Bible  = static::updateFromModuleFile($module, $fields);
        }
    }

    public static function openModuleFileByModule($module) {
        $Bible = static::findByModule($module);

        if($Bible) {
            $Bible->openModuleFile();
        }

        $file_of  = static::getModulePath() . $module . '.zip';
        $file_un  = static::getUnofficialModulePath() . $module . '.zip';

        $Zip = new ZipArchive();

        if($Zip->open($file_of) === TRUE) {
            return $Zip;
        }

        if($Zip->open($file_un) === TRUE) {
            return $Zip;
        }

        return TRUE;
    }

    public function openModuleFile() {
        $Zip  = new ZipArchive();
        $path = $this->getModuleFilePath();

        if($Zip->open($path) === TRUE) {
            return $Zip;
        }

        return FALSE;
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

    public function enable() {
        $this->enabled = 1;
        $this->save();
    }

    public function disable() {
        $this->enabled = 0;
        $this->save();
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
