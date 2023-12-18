<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiIpKeyCount extends Model
{
    use HasFactory;

    protected $table = 'api_ip_key_count';

    protected $fillable = ['key_id', 'ip_id', 'date', 'count', 'limit_reached'];
}
