<?php

namespace App\Traits;

use App\Helpers;

/**
 * Description of Singleton
 *
 */
trait Singleton 
{

    protected static $instance = NULL;

    public static function getInstance() 
    {
        if(!static::$instance) {
            static::$instance = static::generateInstance();
        }

        return static::$instance;
    }

    public static function freshInstance() 
    {
        static::$instance = NULL;
        return static::getInstance();
    }

    /**
     * Discard the current instance without building a replacement.
     * The next getInstance() call constructs one lazily, so callers that only
     * need the stale instance gone (test isolation) do not pay for a new one.
     */
    public static function resetInstance(): void
    {
        static::$instance = NULL;
    }

    public static function generateInstance() 
    {
        $Instance = NULL;

        if(config('app.premium')) {
            $called_class  = get_called_class();
            $premium_class = Helpers::transformClassName($called_class);

            if(class_exists($premium_class)) {
                $Instance = new $premium_class;
            }
        }
        
        $Instance = $Instance ?: new static;
        return $Instance;
    }
}