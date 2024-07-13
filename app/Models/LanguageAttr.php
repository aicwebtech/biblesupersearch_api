<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LanguageAttr extends Model
{
    public $timestamps = false;
    protected $table = 'language_attr';

    protected $fillable = [
        'code', 'attribute', 'value'
    ];
}
