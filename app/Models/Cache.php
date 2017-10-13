<?php

// Note - use App\CacheManager to create cache records

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Cache extends Model {
    protected $table = 'cache';

    protected $fillable = array(
        'hash',
        'form_data',
        'preserve',
    );
}
