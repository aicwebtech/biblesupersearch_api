<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Copyright extends Model {
    protected $fillable = [
        'name', 'cr_name', 'type', 'url', 'desc', 'comments', 'default_copyright_statement', 'version', 'download', 
        'external', 'permission_required', 'attribution_required', 'share_alike', 'non_commercial',  'no_derivatives',  'rank'
    ];

    public function bible() {
        return $this->belongsTo('App\Models\Bible');
    }

    public function getProcessedCopyrightStatement() {
        $cr = $this->default_copyright_statement;

        if($this->type == 'creative_commons') {
            $cr = 'This Bible is made available under the terms of the Creative Commons ';
            $cr .= $this->name;
            $cr .= " license. &nbsp; The terms of this license can be found <a href='{$this->url}'>here</a>";
            $cr .= "&nbsp; This work has been adapted from it's original format to work with Bible SuperSearch.";
        }
        elseif($this->url) {
            $cr .= " &nbsp; The terms of this license can be found <a href='{$this->url}'>here</a>";
        }

        return $cr;
    }

    public static function migrateFromCsv() {
        \App\Importers\Database::importSqlFile('copyrights.sql', NULL, 'copyrights');

        return;

        $map = [
            'name', 'cr_name', 'type', 'url', 'desc', 'comments', 'default_copyright_statement', 'version', 'download', 
            'external', 'permission_required', 'attribution_required', 'share_alike', 'non_commercial',  'no_derivatives',  'rank'
        ];

        // \App\Importers\Database::importCSV('copyrights.csv', $map, '\\' . get_called_class(), 'cr_name');
        \App\Importers\Database::importCSV('copyrights.csv', $map, static::class, 'cr_name');
    }
}
