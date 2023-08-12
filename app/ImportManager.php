<?php

namespace App;

use Illuminate\Http\Request;
use App\Helpers;

class ImportManager {
    use Traits\Error;

    public $test_mode = FALSE;

    // Registry / map of importers that are accessible via HTTP request
    protected static $type_map = [
        'analyzer' => [
            'name'  => 'Bible Analyzer',
            'desc'  => 'Imports a Bible in the Bible Analyzer .bib format.',
            'url'   => 'http://www.bibleanalyzer.com/download.htm',
            'ext'   => ['bib'],
            'kind'  => 'Analyzer',
            'class' => \App\Importers\Analyzer::class,
        ],        
        'biblesupersearch' => [
            'name'  => 'Bible SuperSearch',
            'desc'  => 'Imports a Bible in the Bible SuperSearch .zip format.',
            'url'   => 'https://www.biblesupersearch.com/downloads',
            'ext'   => ['zip'],
            'kind'  => 'BibleSuperSearch',
            'class' => \App\Importers\BibleSuperSearch::class,
        ],
        'csv' => [
            'name'  => 'CSV',
            'desc'  => 'Imports a Bible from a .csv file.',
            'url'   => NULL,
            'ext'   => ['csv'],
            'kind'  => 'Spreadsheet',
            'class' => \App\Importers\Csv::class,
        ],          
        'excel' => [
            'name'  => 'Excel',
            'desc'  => 'Imports a Bible from an Excel spreadsheet.',
            'url'   => NULL,
            'ext'   => ['xls', 'xlsx'],
            'kind'  => 'Spreadsheet',
            'class' => \App\Importers\Spreadsheet::class,
        ],             
        'mysword' => [
            'name'  => 'MySword',
            'desc'  => 'Imports a Bible from an MySword / MyBible module.',
            'url'   => 'https://mysword.info/download-mysword/bibles',
            'ext'   => ['mybible', 'mybible.zip', 'mybible.gz'],
            'kind'  => 'MySword',
            'class' => \App\Importers\MySword::class,
        ],         
        'ods' => [
            'name'  => 'OpenDocument Spreadsheet',
            'desc'  => 'Imports a Bible from an Open Office / LibreOffice spreadsheet.',
            'url'   => NULL,
            'ext'   => ['ods'],
            'kind'  => 'Spreadsheet',
            'class' => \App\Importers\Spreadsheet::class,
        ],        
        'unbound' => [
            'name'  => 'Unbound Bible',
            'desc'  => 'Imports a Bible in the (zipped) Unbound Bible Format',
            'url'   => 'https://www.biblesupersearch.com/unbound-downloads/',
            'ext'   => ['zip'],
            'kind'  => 'Unbound',
            'class' => \App\Importers\Unbound::class,
        ],           
        // 'usx' => [
        //     'name'  => 'USX',
        //     'desc'  => 'Imports a Bible in the (zipped XML) USX Bible Format',
        //     //'url'   => 'https://app.thedigitalbiblelibrary.org/',
        //     'ext'   => ['zip'],
        //     'kind'  => 'Usx',
        //     'class' => \App\Importers\Usx::class,
        // ],          
    ];

    protected static $import_rules = [
        '_importer' => 'required',
        '_file'     => 'required',
        '_settings' => 'nullable',
        '_force_use_module' => 'nullable',
    ];

    public function __construct() {
        // do something?
    }

    public static function getImportersList() {
        $importers = [];

        foreach(static::$type_map as $type => $info) {
            $info['type'] = $type;
            unset($info['class']);
            $importers[]  = $info;
        }

        return $importers;
    }

    public static function getImportRules() {
        $BibleClass = Helpers::find('\App\Models\Bible');

        $rules = $BibleClass::getUpdateRules(NULL);

        foreach(static::$import_rules as $key => $rule) {
            $rules[$key] = $rule;
        }

        return $rules;
    }

    public function setType($type) {
        $type = trim($type);

        if(!$type) {
            $this->type = NULL;
            $this->import_class = NULL;
            return TRUE;
        }

        if(!array_key_exists($type, static::$type_map)) {
            return $this->addError('Import type does not exist: ' . $type);
        }
        
        $class_name = static::$type_map[$type]['class'] ?: NULL;

        if(!$class_name || !class_exists($class_name)) {
            return $this->addError('Import type class does not exist: ' . $type);
        }

        $this->import_class = $class_name;
        $this->type = $type;
        return TRUE;
    }

    public function checkImportFile($data) {
        ini_set('memory_limit', '256M');

        if(!$this->setType($data['importer'])) {
            return FALSE;
        }

        $Importer = new $this->import_class();
        $Importer->test_mode = (bool) $this->test_mode;
        $type_info = static::$type_map[$this->type];
        
        if(!$Importer->setSettings($data)) {
            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        if($data['file']->isValid()) {
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
                        $msg .= 'Extension must be one of the following: .' . implode(', .', $type_info['ext']);
                    }
                    else {
                        $msg .= 'This importer requires an extension of .' . $type_info['ext'][0];
                    }

                    return $this->addError($msg, 4);
                }
            }

            if(!$Importer->acceptUploadedFile($data['file'])) {
                return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
            }
        }
        else {
            return $this->addError('File missing or invalid.  Please note, the maximum upload filesize is ' . Helpers::maxUploadSize());
        }

        $this->sanitized_filename = $Importer->file;
        $this->parsed_attributes  = $Importer->getBibleAttributes();

        foreach($this->parsed_attributes as $key => &$value) {
            if(is_string($value)) {
                $value = iconv("UTF-8","UTF-8//IGNORE", $value);
            }
        }
        unset($value);

        return $this->hasErrors() ? FALSE : TRUE;
    }

    /**
      * Imports a Bible for a given file and importer 
      * 
      */
    public function importFile($data) {
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
        
        $BibleClass = \App\Helpers::find('\App\Models\Bible');
        $Bible      = new $BibleClass();
        $Importer   = new $this->import_class();

        $Bible->fill($data);
        $Bible->save();

        $Importer->module       = $Bible->module;
        $Importer->file         = $file;
        $Importer->overwrite    = FALSE;
        $Importer->insert_into_bible_table = FALSE; // We just saved the Bible above
        
        if(!$Importer->setSettings($settings) || !$Importer->import()) {
            $Bible->delete();
            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        $this->parsed_attributes = $Bible->getAttributes();
        return TRUE;
    }

    protected function _checkModule($module) {
        $res = config('modules_reserved.' . $module);

        if(empty($res)) {
            return TRUE;
        }

        $this->addError('Module \'' . $module . '\' is reserved for \'' . $res['name'] . '\'');
        $this->errors['module_reserved'] = 'Module Reserved';
        $this->errors['module_info']     = $res;

        return FALSE;
    }
}

