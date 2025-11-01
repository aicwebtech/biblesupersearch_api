<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Cache extends Model 
{
    protected $table = 'bible_verses_audio';

    protected $fillable = [
        'hash',
        'hash_long',
        'form_data',
        'preserve',
    ];
}
