<?php

namespace App;

use Illuminate\Http\Request;
use \App\Models\Bible;
use App\Helpers;

class ContentImportManager 
{
    use Traits\Error;

    public $test_mode = FALSE;

    public $content_type = null;
    public $import_source = null;
    public $parsed_attributes = [];
    public $sanitized_filename = NULL;

    // Registry / map of import sources that are accessible via HTTP request
    protected static $import_source_map = [
        'csv' => [
            'name'  => 'CSV',
            'desc'  => 'Imports from a .csv file.',
            'url'   => NULL,
            'ext'   => ['csv'],
            'kind'  => 'Spreadsheet',
        ],          
        'excel' => [
            'name'  => 'Excel',
            'desc'  => 'Imports from an Excel spreadsheet.',
            'url'   => NULL,
            'ext'   => ['xls', 'xlsx'],
            'kind'  => 'Spreadsheet',
        ],             
    ];

    protected static $import_rules = [
        '_importer' => 'required',
        '_content_type' => 'required',
        '_file'     => 'required',
        '_settings' => 'nullable',
        '_force_use_module' => 'nullable',
    ];

    public static function getImportersList() 
    {
        $importers = [];

        foreach(static::$import_source_map as $type => $info) {
            if(is_array($info['desc'])) {
                $info['desc'] = implode(PHP_EOL , $info['desc']);
            }

            $info['type'] = $type;
            $importers[]  = $info;
        }

        return $importers;
    }

    public static function getImportRules($bible_id = null) 
    {
        $BibleClass = Helpers::find('\App\Models\Bible');

        $rules = $BibleClass::getUpdateRules($bible_id);

        foreach(static::$import_rules as $key => $rule) {
            $rules[$key] = $rule;
        }

        return $rules;
    }

    public function setImportSource($type) 
    {
        $type = trim($type);

        if(!$type) {
            $this->import_source = NULL;
            return TRUE;
        }

        if(!array_key_exists($type, static::$import_source_map)) {
            return $this->addError('Import type does not exist: ' . $type);
        }

        $this->import_source = $type;
        return TRUE;
    }

    public function checkImportFile($data) 
    {
        ini_set('memory_limit', '256M');

        if(!isset($data['importer']) || !$this->setImportSource($data['importer'])) {
            return $this->addError('Importer is missing or invalid');
        }

        $Importer = new $this->import_class();
        $Importer->test_mode = (bool) $this->test_mode;
        $type_info = static::$import_source_map[$this->type];
        
        if(!$Importer->setSettings($data)) {
            $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        if(isset($data['file']) && $data['file']->isValid()) {
            $fileinfo = pathinfo( trim($data['file']->getClientOriginalName()) );

            if(is_array($type_info['ext']) && !empty($type_info['ext'])) {
                $matches_ext = FALSE;

                foreach($type_info['ext'] as $e) {
                    if(str_ends_with($fileinfo['basename'], $e)) {
                        $matches_ext = TRUE;
                        break;
                    }
                }

                if(!$matches_ext) {
                    if(count($type_info['ext']) > 1) {
                        $msg = 'Extension must be one of the following: .' . implode(', .', $type_info['ext']);
                    }
                    else {
                        $msg = 'This importer requires an extension of .' . $type_info['ext'][0];
                    }

                    return $this->addError($msg, 4);
                }
            }

            if(!$Importer->acceptUploadedFile($data['file'])) {
                $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
            }
        }
        else {
            $this->addError('File missing or invalid.  Please note, the maximum upload filesize is ' . Helpers::maxUploadSize());
        }

        $this->sanitized_filename = $Importer->file;
        $this->parsed_attributes  = $Importer->getBibleAttributes();

        foreach($this->parsed_attributes as $key => &$value) {
            if(is_string($value)) {
                $value = iconv("UTF-8","UTF-8//IGNORE", $value);
            }
        }
        unset($value);

        if(isset($data['bible_id'])) {
            $Bible = Bible::find($data['bible_id']);
            $this->parsed_attributes = array_replace($this->parsed_attributes, $Bible->attributesToArray());
        }

        return $this->hasErrors() ? FALSE : TRUE;
    }

    /**
      * Imports a Bible for a given file and importer 
      * 
      */
    public function importFile($data, ?Bible $Bible = null) 
    {
        ini_set('memory_limit', '256M');

        $importer = $data['_importer'];
        $file     = $data['_file'];
        $use_mod  = (array_key_exists('_force_use_module', $data) && $data['_force_use_module']);
        $settings = json_decode($data['_settings'], TRUE);

        unset($data['_importer']);
        unset($data['_file']);
        unset($data['_settings']);

        if(!$this->setType($importer)) {
            return FALSE;
        }

        if(!$use_mod && !$this->_checkModule($data['module'])) {
            return FALSE;
        }

        if(!$Bible) {            
            $BibleClass = \App\Helpers::find('\App\Models\Bible');
            $Bible      = new $BibleClass();
        }
        
        $Importer   = new $this->import_class();

        // `_file` is echoed back by the client and is not trusted. Validate it
        // before creating the Bible row so a rejected import leaves nothing behind.
        $safe_file = $Importer->safeImportFileName($file);

        if($safe_file === NULL) {
            return $this->addError('Import file is missing or invalid.  Please upload the file again.');
        }

        $Bible->fill($data);
        $Bible->save();

        $Importer->module       = $Bible->module;
        $Importer->file         = $safe_file;
        $Importer->overwrite    = FALSE;
        $Importer->insert_into_bible_table = FALSE; // We just saved the Bible above
        
        if(!$Importer->setSettings($settings) || !$Importer->import()) {
            $Bible->delete();
            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        $this->parsed_attributes = $Bible->getAttributes();
        return TRUE;
    }

    protected function _checkModule($module) 
    {
        $res = config('modules_reserved.' . $module);

        if(empty($res)) {
            return TRUE;
        }

        $this->addError('Module \'' . $module . '\' is reserved for \'' . $res['name'] . '\'');
        $this->errors['module_reserved'] = 'Module Reserved';
        $this->errors['module_info']     = $res;

        return FALSE;
    }

    /**
     * Returns the import class for a given input source and content type, or NULL if none exists
     *
     * @param string $input_source
     * @param string $content_type
     * @return string|null
     */
    public static function getImportClass(string $input_source, string $content_type): ?string
    {
        if(!array_key_exists($input_source, static::$import_source_map)) {
            return NULL;
        }

        // Get VolumeContent class for this content type, then get the import class for that volume type
        $VolumeTypeClass = \App\Models\Volume::getVolumeTypeClass($content_type);

        // Get the import class for this volume type, if it exists
        $ClassName = $VolumeTypeClass::IMPORT_CLASSES[$input_source] ?? NULL;

        if(!$ClassName || !class_exists($ClassName)) {
            return NULL;
        }

        return $ClassName;
    }
}

