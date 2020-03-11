<?php

namespace App;

use Illuminate\Http\Request;

class ImportManager {
    use Traits\Error;

    protected static $type_map = [];

    public static function getImportersList() {
        $importers = [];

        foreach(static::$type_map as $type => $info) {
            $info['type'] = $type;
            unset($info['class']);
            $importers[]  = $info;
        }

        return $importers;
    }

    public function checkImportFile($data) {
        return $this->addErrorByHttpStatus(501); // not implemented
    }

    public function importFile($data) {
        return $this->addErrorByHttpStatus(501); // not implemented
    }
}
