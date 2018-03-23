<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model {
    public $timestamps = false;
    protected $table = 'config';

    public function getValueAttribute($value) {
        return $value;
    }

    public function setValueAttribute($value) {
        $this->attributes['value'] = $value;
    }
}
