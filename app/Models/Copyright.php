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
     * With a Bible in hand the statement belongs to the Bible, so this hands the whole
     * question to Bible::getCopyrightStatement() - which fills in that Bible's own year and
     * owner and purifies the result. $raw does not apply on that path: the Bible always
     * purifies what it returns.
     *
     * Without one there is no year or owner to fill in, so the generated Creative Commons
     * statement carries the '[year] [owner]' placeholder instead. That is the admin preview,
     * which is also the only caller that wants the statement purified, so $raw is what tells
     * the two apart - Bible::getCopyrightStatement() asks for it raw precisely because it is
     * about to add the real values and purify the whole thing itself.
     *
     * @param Bible|null $Bible The Bible for which the copyright statement is being generated, if applicable. 
     * @param bool $raw If true, returns the raw copyright statement without any HTML sanitization, 
     *             on the assumption that the caller will handle it. If false, returns a sanitized HTML string.
     * @return string|null
     */
    public function getProcessedCopyrightStatement(?Bible $Bible = null, bool $raw = false) 
    {
        if($Bible) {
            return $Bible->getCopyrightStatement();
        }
    
        $cr = $this->default_copyright_statement;

        if($this->type == 'creative_commons') {
            $cr = 'This text is made available under the terms of the ';
            $cr .= $this->name;
            $cr .= " <a href='" . e($this->url) . "' target='_blank'>license</a>.";
            $cr .= "&nbsp; This work has been reformated to work with Bible SuperSearch";
            $cr .= "&nbsp; However, no changes to the text or punctuation have been made.";

            if(!$raw) {
                $cr = 'Copyright &copy; [year] [owner]<br />' . $cr;
            }
        } elseif($this->url) {
            $cr .= " &nbsp; The terms of this license can be found <a href='" . e($this->url) . "' target='_blank'>here</a>";
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
