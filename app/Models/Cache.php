<?php

// Note - use aicwebtech\BibleSuperSearch\CacheManager to create cache records

namespace aicwebtech\BibleSuperSearch\Models;
use Illuminate\Database\Eloquent\Model;

class Cache extends Model {
    protected $table = 'cache';

    protected $fillable = array(
        'hash',
        'form_data',
        'preserve',
    );
}
