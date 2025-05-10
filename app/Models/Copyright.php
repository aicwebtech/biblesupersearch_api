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

    public function getProcessedCopyrightStatement(?Bible &$Bible = null) 
    {
        $cr = $this->default_copyright_statement;
        $include_year_pub = false;

        if($this->type == 'creative_commons') {
            $cr = 'This Bible is made available under the terms of the ';
            $cr .= $this->name;
            $cr .= " <a href='{$this->url}' target='_NEW'>license</a>.";
            $cr .= "&nbsp; This work has been reformated to work with Bible SuperSearch";
            $cr .= "&nbsp; However, no changes to the text or punctuation have been made.";
            $include_year_pub = true;
        }
        elseif($this->url) {
            $cr .= " &nbsp; The terms of this license can be found <a href='{$this->url}' target='_NEW'>here</a>";
        }

        if($include_year_pub) {
            if(!$Bible) {
                $yp_text = 'Copyright &copy; [year] [owner]';
            } else {
                $yr = $Bible->year;
                $ow = $Bible->owner;

                if(!$yr && !$ow) {
                    $yp_text = null;
                } else {
                    $yp_text = 'Copyright &copy;';
                    $yp_text .= ($yr) ? ' ' . $yr : '';
                    $yp_text .= ($ow) ? ' ' . $ow : '';
                }
            }

            if($yp_text) {
                $cr = $yp_text . '<br /><br />' . $cr;
            }
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
