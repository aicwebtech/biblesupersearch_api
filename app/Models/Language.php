<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model {

    public $timestamps = FALSE;

    public function rtl() {
        return static::isRtl($this->code);
    }

    public static function isRtl($lang) {
        return ($lang == 'he' || $lang == 'ar') ? TRUE : FALSE;
    }
}
