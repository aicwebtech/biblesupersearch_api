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

}
