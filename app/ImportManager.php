<?php

namespace App;

use Illuminate\Http\Request;
use App\Helpers;

class ImportManager {
    use Traits\Error;

    protected static $type_map = [];
    protected static $import_rules = [];

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

    public function checkImportFile($data) {
        return $this->addErrorByHttpStatus(501); // not implemented
    }

    public function importFile($data) {
        return $this->addErrorByHttpStatus(501); // not implemented
    }
}
