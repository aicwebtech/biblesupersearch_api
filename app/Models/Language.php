<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Books\BookAbstract as Book;

class Language extends Model 
{

    public $timestamps = FALSE;

    protected $fillable = [
        'name', 'iso_name', 'code', 'native_name', 'iso_endonym', 'rtl', 'family', 
        'iso_639_1', 'iso_639_2', 'iso_639_2_b', 'iso_639_3', 'iso_639_3_raw', 'notes',
        'common_words',
    ];

    public function rtl() 
    {
        return (bool) $this->rtl;
        // return static::isRtl($this->code);
    }

    public function setRtlAttribute($value) 
    {
        $lv = strtolower($value);
        $this->attributes['rtl'] = ($value && $lv != 'false' && $lv != 'no') ? 1 : 0;
    }

    public function setIso6393RawAttribute($value) 
    {
        $value = trim((string)$value);
        $this->attributes['iso_639_3_raw'] = $value;
        $this->attributes['iso_639_3']     = substr($value, 0, 3);
    }

    public function getNativeNameAttribute($value) 
    {
        return is_string($value) ? ucwords($value) : null;
    }
    
    public function _setIso6391Attribute($value) 
    {
        $value = trim((string)$value);    
        $this->attributes['iso_639_1'] = $value;
        $this->_defaultSetCode($value);
    }

    protected function _defaultSetCode($value) {
        if($value && !$this->attributes['code']) {
            $this->attributes['code'] = $value;
        }
    }

    public function getCommonWordsAsArray()
    {
        return preg_split("/\r\n|\n|\r/", strtolower($this->common_words));
    }

    public function formatEnglishName()
    {
        if($this->name == $this->native_name) {
            return $this->name;
        } else {
            return $this->native_name . ' (' . $this->name . ')';
        }
    }

    public function formatNameCode() 
    {
        return $this->name . ' (' . strtoupper($this->code) . ')';
    }

    public function getAllCodes()
    {
        return ($this->code == 'zh') ? ['zh', 'zh_cn', 'zh_tw'] : [$this->code];
    }

    public function initLanguage()
    {
        $attr = $this->getAttrAll();
        $codes = $this->getAllCodes();

        if(!isset($attr['book_list'])) {
            $added = false;

            foreach($codes as $code) {
                $added = Book::createTableAndMigrateFromCsv($code) ? true : $added;
            }

            if($added) {
                $this->setAttr('book_list', '1');
            }
        }
    }

    public function denitLanguage()
    {
        foreach($this->getAllCodes() as $code) {
            Book::dropBookTable($code);
        }
    }

    public function getAttrAll()
    {
        $raw = LanguageAttr::where('code', $this->code)->get()->all();

        return array_column($raw, 'value', 'attribute');
    }

    public static function getLanguageAttr($lang, $attr, $default = null) 
    {
        $raw = LanguageAttr::where('code', $lang) 
                -> where('attribute', $attr)
                -> value('value');

        return $raw === null ? $default : $raw;
    }

    public static function hasBookSupport($lang)
    {
        return (bool)static::getLanguageAttr($lang, 'book_list');
    }

    public static function haveBookSupport()
    {
        return static::haveSupport('book_list');
    }

    public static function haveSupport($attribute)
    {
        LanguageAttr::where('attribute', $attribute)->pluck('code');
    }

    public function setAttr($attribute, $value)
    {
        LanguageAttr::updateOrCreate([
            'code'      => $this->code, 
            'attribute' => $attribute
        ], [
            'value'     => $value,
        ]);
    }

    public static function migrateFromCsv() 
    {
        $map = [
            'name', 'native_name', 'iso_name', 'code', 'iso_endonym', 'rtl|boolstr', 'family',
            'iso_639_1', 'iso_639_2', 'iso_639_2_b', 'iso_639_3_raw', 'notes'
        ];

        \App\Importers\Database::importCSV('languages.csv', $map, '\\' . get_called_class(), 'code', null, 100);
    }    

    public static function migrateFromCsv2() 
    {
        $map = [
            'code', 'name', 'native_name', 'iso_name', 'iso_639_2', 'rtl|boolstr', 'notes'
        ];

        \App\Importers\Database::importCSV('languages_2.csv', $map, '\\' . get_called_class(), 'code', null, 100);
    }

    public static function isRtl($lang) 
    {
        $Language = static::where('code', $lang)->first();

        if(!$Language) {
            return FALSE;
        }

        return (bool) $Language->rtl;
    }

    public static function findByCode($code, $fail = false) {
        if($fail) {
            $Language = static::where('code', $code)->firstOrFail();
        } else {
            $Language = static::where('code', $code)->first();
        }

        return $Language ?: NULL;
    }
}
