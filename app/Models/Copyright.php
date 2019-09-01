<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Copyright extends Model {

    public function bible() {
        return $this->belongsTo('App\Models\Bible');
    }
}
