<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKeyAccessLog extends Model
{
    use HasFactory;

    protected $table = 'api_key_access_log';

    protected $fillable = ['key_id', 'date', 'count', 'limit_reached'];
}
