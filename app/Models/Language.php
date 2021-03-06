<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model {

    public $timestamps = FALSE;

    protected $fillable = [
        'name', 'iso_name', 'code', 'native_name', 'iso_endonym', 'rtl', 'family', 'iso_639_1', 'iso_639_2', 'iso_639_2_b', 'iso_639_3', 'iso_639_3_raw', 'notes'
    ];

    public function rtl() {
        return $this->rtl ? TRUE : FALSE;
        // return static::isRtl($this->code);
    }

    public function setRtlAttribute($value) {
        $lv = strtolower($value);
        $this->attributes['rtl'] = ($value && $lv != 'false' && $lv != 'no') ? 1 : 0;
    }

    public function setIso6393RawAttribute($value) {
        $value = trim($value);
        $this->attributes['iso_639_3_raw'] = $value;
        $this->attributes['iso_639_3']     = substr($value, 0, 3);
    }
    
    public function _setIso6391Attribute($value) {
        $value = trim($value);    
        $this->attributes['iso_639_1'] = $value;
        $this->_defaultSetCode($value);
    }

    protected function _defaultSetCode($value) {
        if($value && !$this->attributes['code']) {
            $this->attributes['code'] = $value;
        }
    }

    public static function migrateFromCsv() {
        $map = [
            'name', 'native_name', 'iso_name', 'code', 'iso_endonym', 'rtl', 'family', 'iso_639_1', 'iso_639_2', 'iso_639_2_b', 'iso_639_3_raw', 'notes'
        ];

        \App\Importers\Database::importCSV('languages.csv', $map, '\\' . get_called_class(), 'code');
    }

    public static function isRtl($lang) {
        $Language = static::where('code', $lang)->first();

        if(!$Language) {
            return FALSE;
        }

        return ($Language->rtl) ? TRUE : FALSE;
    }

    public static function findByCode($code) {
        $Language = static::where('code', $code)->first();
        return $Language ?: NULL;
    }
}
