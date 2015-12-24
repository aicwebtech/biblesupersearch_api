<?php

namespace App\Models\Verses;

use Illuminate\Database\Eloquent\Model;
use App\Models\Bible;

// Verses models should only be instantiated from within a Bible model instance
// Abstraction allows for the potential for Bibles other than the 'standard' format
// However, actual support for non-standard formats won't be implemented any time soon.

abstract class Abs extends Model
{
    //protected App\Models\Bible $Bible; //
}
