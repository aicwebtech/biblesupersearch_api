<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiAccessLevel extends Model
{
    use HasFactory;

    const NONE  = 1; // No Access
    const BASIC = 2; // Basic Access
    const KEYED = 3; // Keyed acess - user defined
    const FULL  = 4; // Full, unlimited access

    protected $guard = ['id', 'system_name', 'can_edit'];
}
