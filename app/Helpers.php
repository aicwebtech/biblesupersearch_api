<?php

namespace App;

class Helpers {

    /*
     * Sorts an array of strings by string length
     */
    public static function sortStringsByLength(&$array, $dir = 'DESC') {
        return usort($array, function($a, $b) use ($dir) {
            $comp = strlen($a) <=> strlen($b);
            $comp = ($dir == 'DESC') ? $comp * -1 : $comp;
            return $comp;
        });
    }

    /* 
     * Check to see if premium code is present and enabled
     */
    public static function isPremium() {
        if(config('app.premium_disabled')) {
            return FALSE;
        }

        return static::premiumCodePresent();
    }

    /* 
     * Check to see if premium code is present and enabled
     */
    public static function premiumCodePresent() {

        // List of classes with known premium versions
        $classes = [
            'Engine',
        ];

        foreach($classes as $basename) {
            $class_name = 'App\Premium\\' . $basename;

            if(!class_exists($class_name)) {
                return FALSE;
            }
        }

        return TRUE;
    }

    public static function make($class_name) {
        $new_class_name = static::find($class_name);
        return new $new_class_name();
    }

    public static function find($class_name) {
        $new_class_name = static::transformClassName($class_name);

        if(class_exists($new_class_name)) {
            return $new_class_name;
        }
        else if(class_exists($class_name)) {
            return $class_name;
        }

        return FALSE;
    }

    public static function transformClassName($class_name) {
        $imp = \App\InstallManager::getImportableDir()[2];
        $class_name_imp = str_replace("App\\", "App\\" . $imp . "\\", $class_name);
        
        if(class_exists($class_name_imp)) {
            return $class_name_imp;
        }

        return config('app.premium') ? str_replace("App\\", "App\Premium\\", $class_name) : $class_name;
    }

    public static function ordinal($number) {
        $ends = ['th','st','nd','rd','th','th','th','th','th','th'];
        
        if ((($number % 100) >= 11) && (($number % 100) <= 13)) {
            return $number. 'th';
        }
        else {
            return $number. $ends[$number % 10];
        }
    }

    public static function isCommonWord($word, $lang) {
        $common_en = ['a', 'and', 'the', 'or', 'but'];

        if($lang == 'en' && in_array($word, $common_en)) {
            return TRUE;
        }

        return FALSE;
    }

    public static function maxUploadSize($format = TRUE) {
        $max = \Illuminate\Http\UploadedFile::getMaxFilesize();
        $max_fmt = NULL;

        if(!$format) {
            return $max;
        }

        $map = [
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'k' => 1024
        ];

        foreach($map as $k => $v) {
            if($max >= $v) {
                $max_fmt = $max / $v;
                $max_fmt .=  $k;
                break;
            }
        }

        $max_fmt = $max_fmt ?: $max;
        return ($format === 'both') ? ['raw' => $max, 'fmt' => $max_fmt] : $max_fmt;
    }

    public static function isAuthorized($access_level) {

    }

    public static function buildSearchQuery($data, &$Query) {
        $val = $data['searchString'];
        $op  = NULL;

        switch($data['searchOper']) {
            case 'eq':
                $op = '=';
                break;             
            case 'ne':
                $op = '!=';
                break;           
            case 'lt':
                $op = '<';
                break;             
            case 'le':
                $op = '<=';
                break;
            case 'gt':
                $op = '>';
                break;             
            case 'le':
                $op = '>=';
                break; 
            case 'bw':
                $op = 'LIKE';
                $val .= '%';
                break;             
            case 'bn':
                $op = 'NOT LIKE';
                $val .= '%';
                break;            
            case 'ew':
                $op = 'LIKE';
                $val = '%' . $val;
                break;             
            case 'en':
                $op = 'NOT LIKE';
                $val = '%' . $val;
                break;             
            case 'cn':
                $op = 'LIKE';
                $val = '%' . $val . '%';
                break;             
            case 'nc':
                $op = 'NOT LIKE';
                $val = '%' . $val . '%';
                break; 
        }

        $sql = $data['searchField'] . ' ' . $op . ' \'' . $val . '\'';

        if($op && $data['searchString'] != '_no_rest_') {
            $Query->where($data['searchField'], $op, $val);
        }

        return $sql;
    }

}
