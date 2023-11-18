<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ApiAccessLevel extends Model
{
    use HasFactory;

    const NONE  = 1; // No Access
    const BASIC = 2; // Basic Access only
    const KEYED = 3; // Keyed acess - user defined
    const FULL  = 4; // Full, unlimited access

    protected $guard = ['id', 'system_name', 'can_edit'];


    public function hasBasicAccess()
    {
        return !$this->hasNoAccess();
    }

    public function hasNoAccess()
    {
        return $this->id == static::NONE || $this->limit < 0;
    }

    public function hasActionAccess($action) 
    {
        if($this->id == static::FULL) {
            return true;
        }

        if($this->hasNoAccess()) {
            return false;
        }

        switch($action) {
            case 'statistics':
            case 'commentaries':
            case 'dictionaries':
                return (bool)$this->$action;
                break;
            default:
                return true;
        }
    }
}
