<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Bible;

class Copyright extends Model 
{
    protected $fillable = [
        'name', 'cr_name', 'type', 'url', 'desc', 'comments', 'default_copyright_statement', 'version', 'download', 
        'external', 'permission_required', 'attribution_required', 'share_alike', 'non_commercial',  'no_derivatives',  'rank'
    ];

    public function bible() 
    {
        return $this->belongsTo('App\Models\Bible');
    }

    /**
     * Builds the copyright statement shown for a text that has none of its own.
     *
     *
     * @param bool $raw If true, returns the raw copyright statement without any HTML sanitization, 
     *             on the assumption that the caller will handle it. If false, returns a sanitized HTML string.
     * @return string|null
     */
    public function getProcessedCopyrightStatement(bool $raw = false) 
    {
        $cr = $this->default_copyright_statement;

        if($this->type == 'creative_commons') {
            $cr = 'This Bible is made available under the terms of the ';
            $cr .= $this->name;
            $cr .= " <a href='" . e($this->url) . "' target='_NEW'>license</a>.";
            $cr .= "&nbsp; This work has been reformated to work with Bible SuperSearch";
            $cr .= "&nbsp; However, no changes to the text or punctuation have been made.";
        } elseif($this->url) {
            $cr .= " &nbsp; The terms of this license can be found <a href='" . e($this->url) . "' target='_NEW'>here</a>";
        }

        if(!$raw) {
            $cr = \App\Helpers::sanitizeHtml($cr);
        }

        return $cr;
    }

    public static function migrateFromCsv() 
    {
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
