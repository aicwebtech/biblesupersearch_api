<?php

namespace App;

class ImportManager {
    // not yet implemented
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
}
