<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use App\Models\Verses\VerseStandard As StandardVerses;
use App\Models\Language;
use App\Passage;
use App\Search;
use Illuminate\Support\Arr;
use ZipArchive;
use App\Traits\Error;

class Bible extends Model 
{
    use Error;

    static $_cache = [];

    static public function getUpdateRules($bible_id = NULL) 
    {
        $bible_id = (int) $bible_id;

        $rules = array(
            'name'      => [
                'required',
                'max:255',
                Rule::unique('bibles')->ignore($bible_id),
            ],
            'shortname' => [
                'required',
                'max:255',
                Rule::unique('bibles')->ignore($bible_id),
            ],
            'year'      => 'nullable',
            'rank'      => 'sometimes|required|int',
            'module'        => [
                'required',
                Rule::unique('bibles')->ignore($bible_id),
                function($attribute, $value, $fail) {
                    $valid = static::validateModule($value);

                    if(!$valid) {
                        $fail('Module can contain only lowercase letters, numbers, and underscores.  The first two characters must be letters');
                    }
                },
                'max:100'
            ],
            'lang_short'            => 'required|alpha|min:2|max:3',
            'owner'                 => 'nullable',
            'publisher'             => 'nullable',
            'restrict'              => 'nullable',
            'research'              => 'nullable',
            'description'           => 'nullable',
            'copyright_statement'   => 'nullable',
            'copyright_id'          => 'required|integer',
        );    

        return $rules;
    }

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
        'restrict',
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
        'copyright_id'  => NULL,
        'rank'          => 1000,
    ];

    // List of fields to NOT export when creating modules
    protected $do_not_export = ['id', 'created_at', 'updated_at', 'enabled', 'installed', 'installed_at', 'needs_update', 'module_updated_at'];

    // List of fileds to not use as metadata (in addition to those contained in $this->do_not_export)
    protected $do_not_meta = ['rank', 'module_v2', 'importer', 'import_file', 'copyright_id', 'hebrew_text_id', 'greek_text_id', 'translation_type_id'];

    public $migrate_code = 0;  // 0 = no change, 1 = deleted unnessessary file, 2 = moved file, 3 = file does not exist

    /**
     * Create a new Bible Instance
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = []) 
    {
        parent::__construct($attributes);
    }

    public function language() 
    {
        return $this->hasOne('App\Models\Language', 'code', 'lang_short');
    }

    /**
     * Mimic a DB relationship
     * 'One to TABLE' relationship
     * Each Bible record points to an entire DB table
     */
    public function verses($force = FALSE) 
    {
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
    public function getSearch($Passages = NULL, $Search = NULL, $parameters = array()) 
    {
        return $this->verses()->getSearch($Passages, $Search, $parameters);
    }

    /**
     * Processes and executes the Bible statistics query
     *
     * @param array $Passages Array of App/Passage instances, represents the passages requested, if any
     * @param array $parameters Search parameters - user input
     * @return array $Verses array of Verses instances (found verses)
     */
    public function getStatistics($Passages = NULL, $parameters = []) 
    {
        return $this->verses()->getStatistics($Passages, $parameters);
    }

    public function getVersesByBCV($bcv) 
    {
        return $this->verses()->getVersesByBCV($bcv);
    }

    public function install($structure_only = FALSE, $enable = FALSE) 
    {
        if (!$this->installed) {
            $success = $this->verses()->install($structure_only);

            if(!$success) {
                $this->addError('Could not install Bible table', 4);
            }
            else {
                $this->installed = 1;
                $this->installed_at = date('Y-m-d H:i:s');
                $this->module_updated_at = NULL;
                $this->needs_update = 0;
                $this->module_version = config('app.version');

                if($enable) {
                    $this->enabled = 1;
                }

                $this->save();

                $Lang = Language::findByCode($this->lang_short);
                $Lang && $Lang->initLanguage();
            }
        }
        else {
            $this->addError('Already installed', 1);
        }
    }

    public function uninstall() 
    {
        if ($this->installed) {
            $this->verses()->uninstall();
            $this->installed = 0;
            $this->enabled = 0;
            $this->installed_at = NULL;
            $this->module_updated_at = NULL;
            $this->save();
        }
        else {
            $this->addError('Already uninstalled', 1);
        }
    }

    public function export($overwrite = FALSE, $path = null) 
    {
        $path = $path ?: $this->getModuleFilePath();

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

        $this->installed_at = date('Y-m-d H:i:s');
        $this->save();
        return ($res === TRUE);
    }

    protected function _getExportInfo() 
    {
        $info = Arr::except($this->attributes, $this->do_not_export);
        $info['delimiter'] = static::getExportDelimiter(); // Store this in case we change it in the future
        $info['fields']    = static::getExportFields();
        $info = json_encode($info);
        return $info;
    }

    public function getInfo() 
    {
        return Arr::except($this->attributes, $this->do_not_export);
    }

    public function getMeta() 
    {
        $exclude = array_merge($this->do_not_export, $this->do_not_meta);
        return Arr::except($this->attributes, $exclude);
    }

    public function isDownloadable() 
    {
        if($this->restrict || !$this->copyright_id) {
            return FALSE;
        }

        if(!$this->copyrightInfo || !$this->copyrightInfo->download) {
            return FALSE;
        }

        return TRUE;
    }

    public function setCopyrightStatementAttribute($value) 
    {
        $this->attributes['copyright_statement'] = trim($value);
    }

    public function getCopyrightStatement() 
    {
        if($this->copyright_statement) {
            return $this->copyright_statement;
        }

        if($this->copyright_id && $this->copyrightInfo) {
            return $this->copyrightInfo->getProcessedCopyrightStatement($this);
        }

        return $this->description;
    }

    public function copyrightInfo() 
    {
        return $this->hasOne('App\Models\Copyright', 'id', 'copyright_id');
    }

    public function updateMetaInfo($create_if_needed = FALSE) 
    {
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

        $this->installed_at = date('Y-m-d H:i:s');
        $this->save();
        return ($res === TRUE);
    }    

    public function revertMetaInfo() 
    {
        $path = $this->getModuleFilePath();

        if(!is_file($path)) {
            return $this->addError('Cannot revert info, file does not exist', 4);
        }

        if(!is_readable($path)) {
            return $this->addError('Cannot read file: ' . $this->getModuleFilePathShort() . ' as user ' . exec('whoami'), 4);
        }

        $Zip  = new ZipArchive();
        $res  = $Zip->open($path);

        if($res === TRUE) {
            $json  = $Zip->getFromName('info.json');
            $attr  = json_decode($json, TRUE);

            $this->fill($attr);
            $this->save();
            $Zip->close();
        }

        return ($res === TRUE);
    }

    public function migrateModuleFile($dry_run = FALSE) 
    {
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

    public function deleteModuleFile($include_official = FALSE) 
    {
        $path_of = static::getModulePath();
        $path_un = static::getUnofficialModulePath();

        $file_path_of = $path_of . $this->getModuleFileName();
        $file_path_un = $path_un . $this->getModuleFileName();

        if($include_official && is_file($file_path_of)) {
            unlink($file_path_of);
        }        

        if(is_file($file_path_un)) {
            unlink($file_path_un);
        }
    }

    public function deleteRenderedFiles() {
        $Renderings = \App\Models\Rendering::where('module', $this->module)->get();

        foreach($Renderings as $R) {
            $R->deleteRenderedFile();
            $R->delete();
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

    public function needsUpdate_OLD() {
        return FALSE;

        if(!$this->hasModuleFile()) {
            return FALSE;
        }

        $install_ts = strtotime($this->installed_at);
        $update_ts  = filemtime($this->getModuleFilePath());

        if(!$install_ts || !$update_ts) {
            return FALSE;
        }

        if($update_ts > $install_ts) {
            return TRUE;
        }

        return FALSE;
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
        return ($Bible && $Bible->enabled);
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
        if(!$module) {
            return FALSE;
        }

        $Bible = static::findByModule($module);
        $Zip   = static::openModuleFileByModule($module);

        if($Bible) {
            return FALSE;
        }

        if($Zip === TRUE) {
            throw new \Exception('Could not open zip file for ' . $module);
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
        if(!$module) {
            return FALSE;
        }

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
     * Adds Bible records for Bibles *not existing* in database
     * Does NOT overwrite existing module data
     * 
     * In the case of a module conflict between a new, official module and pre-existing unofficial module,
     * the new module will be ignored.  If the pre-existing module is ever deleted, the official module
     * will appear in it's place.
     */
    public static function populateBibleTable() 
    {
        $list = static::getListOfModuleFiles();

        foreach($list as $file) {
            $module = substr($file, 0, strlen($file) - 4);
            $Bible  = static::createFromModuleFile($module);
        }
    }

    public static function updateNeedsUpdate()
    {
        $Bibles = static::all();
        
        foreach($Bibles as $Bible) {
            $Bible->needsUpdate();
        }
    }

    /**
     * NOTE: This method not currently used.
     * IF it is ever used, will need to ensure that the same logic 
     * for module confilct is used as for self::populateBibleTable above
     * 
     */ 
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
            $Zip = $Bible->openModuleFile();
            return $Zip ?: TRUE;
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

    // Stub method to check if a module has files in both the official and unofficial directory
    public static function isModuleConflicted($module) {
        $Bible = static::findByModule($module);

        $file_of  = static::getModulePath() . $module . '.zip';
        $file_un  = static::getUnofficialModulePath() . $module . '.zip';

        $Zip_Of = new ZipArchive();
        $Zip_Un = new ZipArchive();

        $has_official = $has_unofficial = $official_at_fault = FALSE;

        if($Zip_Of->open($file_of) === TRUE) {
           $has_official = TRUE;
        }

        if($Zip_Un->open($file_un) === TRUE) {
            $has_unofficial = TRUE;
        }

        if(!$has_official || !$has_unofficial) {
            return FALSE; // no conflict
        }

        if($Bible && !$Bible->official) {
            // In this case, the UNOFFICIAL module prevails
            // todo: flag Bible as conflicted
            $official_at_fault = TRUE;
        }

        if($Bible && $Bible->official) {
            // In this case, the official module prevails
            // todo: do something?
        }

        if(!$Bible) {
            // In this case, the official module prevails
            // todo: do something?
        }

        return TRUE;
    }

    public function openModuleFile() 
    {
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
    public static function getVerseClassNameByModule($module) 
    {
        if(!static::validateModule($module)) {
            return FALSE;
        }

        $model_class = studly_case($module);
        $namespace = __NAMESPACE__ . '\Verses';
        $class_name = $namespace . '\\' . $model_class;

        if (!class_exists($class_name)) {
            $table = StandardVerses::getTableByModule($module);
            $perm_file = (func_num_args() >= 2) ? func_get_arg(1) : FALSE;

            $code = '
                namespace ' . $namespace . ';
                class ' . $model_class . ' extends VerseStandard {
                    protected $hasClass = FALSE;
                    protected $table = \'' . $table . '\';
                }
            ';

            if($perm_file && is_writable(dirname(__FILE__) . '/Verses')) {
                // Create permanent class file and include it
                $filepath = dirname(__FILE__) . '/Verses/' . $model_class . '.php';
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

        return $class_name;
    }

    public static function validateModule($module) 
    {
        if(empty($module)) {
            return FALSE;
        }

        if(preg_match('/[^a-z_0-9]/', $module)) {
            return FALSE;
        }        

        if(!preg_match('/^[a-z]{2}/', $module)) {
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Determine the class name for the Verses model for the current Bible instance
     * @return string $class_name;
     */
    public function getVerseClassName() 
    {
        return self::getVerseClassNameByModule($this->module);
    }

    /**
     * Enabled mutator
     * @param string $value
     */
    public function setEnabledAttribute($value) 
    {
        $this->attributes['enabled'] = ($this->installed) ? $value : 0;
    }

    public function enable() 
    {
        $this->enabled = 1;
        $this->save();
    }

    public function disable() 
    {
        $this->enabled = 0;
        $this->save();
    }

    /**
     * Module mutator
     * @param string $value
     */
    public function setModuleAttribute($value) 
    {
        // $matched = preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $value, $matches);
        $value = strtolower($value);
        $this->attributes['module'] = $value;
        // self::where('1','1')->get();
    }

    public function needsUpdate() 
    {
        if(!$this->installed) {
            return FALSE;
        }

        if(!$this->module_version || version_compare($this->module_version, config('app.version')) >= 0) {
            if($this->module_version != config('app.version') || $this->needs_update == 1) {  
                $this->module_version = config('app.version');
                $this->needs_update = 0;
                $this->save();
            }

            return FALSE;
        }
        else if($this->needs_update) {
            return TRUE;
        }

        $Zip  = $this->openModuleFile();

        if(!$Zip) {
            return FALSE;
        }
        
        $json = $Zip->getFromName('info.json');
        $meta = json_decode($json, TRUE);

        if(array_key_exists('module_version', $meta) && version_compare($this->module_version, $meta['module_version']) == -1) {
            if($this->needs_update == 0) {                
                $this->needs_update = 1;
                $this->save();
            }

            return TRUE;
        }
        else {
            if($this->module_version != config('app.version') || $this->needs_update == 1) {                
                $this->module_version = config('app.version');
                $this->needs_update = 0;
                $this->save();
            }

            return FALSE;
        }
    }

    public function getRandomReference($random_mode) {
        return $this->verses()->getRandomReference($random_mode);
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes() {
        return [
            'copyright_id' => 'copyright',
        ];
    }

    /**
     * Gets the count of chapters in each book, and verses in each chapter
     * 
     * @return array
     */
    public function getChapterVerseCount($verbose = FALSE) {    
        return $this->verses()->getChapterVerseCount($verbose);
    }
}
