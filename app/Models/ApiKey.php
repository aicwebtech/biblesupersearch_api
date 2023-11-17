<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    static public function findByKey($key, $fail) 
    {
        if($fail) {
            return static::where('key', $key)->firstOrFail();
        } else {
            return static::where('key', $key)->first();
        }
    }
}
