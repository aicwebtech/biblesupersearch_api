<?php

namespace App\Traits;

use App\Helpers;

/**
 * Description of Singleton
 *
 */
trait Singleton {

    protected static $instance = NULL;

    public static function getInstance() {
        if(!static::$instance) {
            static::$instance = static::generateInstance();
        }

        return static::$instance;
    }

    public static function generateInstance() {
        $Instance = NULL;

        if(config('app.premium')) {
            $called_class  = get_called_class();
            $premium_class = Helpers::transformClassName($called_class);

            // var_dump($called_class);
            // var_dump($premium_class);

            if(class_exists($premium_class)) {
                $Instance = new $premium_class;
            }
        }
        
        $Instance = $Instance ?: new static;

        return $Instance;
    }
}