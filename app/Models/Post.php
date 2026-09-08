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
     * Content accessor / mutator.
     *
     * This is the Terms of Service / privacy body: written through the CKEditor field in
     * admin/postconfig.blade.php and echoed unescaped by resources/views/docs/{tos,privacy}.php,
     * so it is the one Post column that carries HTML. The column is nullable, and unlike the
     * Bible columns a NULL is stored back as NULL rather than as the empty string.
     */
    protected function content(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => Helpers::sanitizeHtml($value),
            set: fn (?string $value) => ($value === NULL) ? NULL : Helpers::sanitizeHtml($value),
        );
    }
}
