<?php

namespace App\Engines;

use App\Engine as BaseEngine;

// This class is just a stub as App\Engine contains all the V2 code.

class EngineV2 extends BaseEngine
{
    /**
     * Redeclared so this engine gets its own singleton slot. Traits\Singleton declares
     * $instance on the trait, which App\Engine uses, so without this every engine in the
     * hierarchy would share the one instance and the factory would hand back whichever
     * version was asked for first.
     */
    protected static $instance = NULL;

    protected static $api_version = 2;
}
