<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Helpers;

class Post extends Model 
{
    use SoftDeletes;    

    /**
     * Content accessor.
     *
     * This is the Terms of Service / privacy body: written through the CKEditor field in
     * admin/postconfig.blade.php and echoed unescaped by resources/views/docs/{tos,privacy}.php,
     * so it is the one Post column that carries HTML.
     *
     * Read-time only, deliberately. The editor ships the image, horizontal-line, highlight,
     * strikethrough, code, page-break and font plugins, and none of 'img', 'figure', 'hr',
     * 'mark', 's' or 'code' survives SANITIZE_HTML_ALLOWED - so a mutator would strip an
     * admin's image the moment they pressed Save and write the loss back over the column,
     * with nothing to restore it from. Sanitizing on the way out protects the page just as
     * well and keeps what was typed.
     *
     * The column is nullable and a NULL stays NULL.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value === NULL) ? NULL : Helpers::sanitizeHtml($value),
        );
    }
}
