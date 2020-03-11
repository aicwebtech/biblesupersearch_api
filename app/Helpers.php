<?php

namespace aicwebtech\BibleSuperSearch;

class Helpers {

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
            $class_name = 'aicwebtech\BibleSuperSearch\Premium\\' . $basename;

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
        return config('app.premium') ? str_replace("aicwebtech\BibleSuperSearch\\", "aicwebtech\BibleSuperSearch\Premium\\", $class_name) : $class_name;
    }

}
