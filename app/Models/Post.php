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
     * sanitizeEditorHtml(), not sanitizeHtml(): admin/postconfig.blade.php reads this column
     * back into CKEditor through this very accessor, so the editor allowlist is what decides
     * what the administrator sees when the page loads - and therefore what PostConfigController
     * writes back when they press Save without touching anything. Against the API allowlist
     * that round trip silently destroyed every image, rule and code span in the document.
     *
     * Read-time only, deliberately: with no mutator, whatever is in the column stays there
     * until an administrator actually saves the form, so the original survives this change.
     *
     * The column is nullable and a NULL stays NULL.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => Helpers::sanitizeEditorHtml($value),
        );
    }
}
