<?php

namespace App;

use Illuminate\Http\Request;
use App\Helpers;

class ImportManager {
    use Traits\Error;

    // List / map of importers that are accessible via HTTP request
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
            'ext'   => ['mybible'],
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
            'url'   => 'https://unbound.biola.edu/index.cfm?method=downloads.showDownloadMain',
            'ext'   => ['zip'],
            'kind'  => 'Unbound',
            'class' => \App\Importers\Unbound::class,
        ],          
    ];

    protected static $import_rules = [
        '_importer' => 'required',
        '_file'     => 'required',
        '_settings' => 'nullable',
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

        if(!static::$type_map[$type]) {
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
        if(!$this->setType($data['importer'])) {
            return FALSE;
        }

        $Importer = new $this->import_class();
        $type_info = static::$type_map[$this->type];
        
        if(!$Importer->setSettings($data)) {
            return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
        }

        if($data['file']->isValid()) {
            $fileinfo = pathinfo( trim($data['file']->getClientOriginalName()) );

            if(!in_array($fileinfo['extension'], $type_info['ext'])) {
                $msg = 'Invalid file extension: .' . $fileinfo['extension'] . '; ';

                if(count($type_info['ext']) > 1) {
                    $msg .= 'Extension must be one of the following: .' . implode(', .', $type_info['ext']);
                }
                else {
                    $msg .= 'This importer requires an extension of .' . $type_info['ext'][0];
                }

                return $this->addError($msg, 4);
            }

            if(!$Importer->acceptUploadedFile($data['file'])) {
                return $this->addErrors($Importer->getErrors(), $Importer->getErrorLevel());
            }
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
        $importer = $data['_importer'];
        $file     = $data['_file'];
        $settings = json_decode($data['_settings'], TRUE);

        unset($data['_importer']);
        unset($data['_file']);
        unset($data['_settings']);

        if(!$this->setType($importer)) {
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
}

