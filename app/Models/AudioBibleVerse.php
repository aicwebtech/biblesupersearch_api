<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AudioBibleVerse extends Model 
{
    protected $table = 'bible_verses_audio';

    protected $fillable = [
        'hash',
        'hash_long',
        'form_data',
        'preserve',
    ];
}
