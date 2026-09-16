<?php

namespace App\Factories;

class EngineFactory
{
    protected static $default_api_version = 2;

    public static function getClassName($version = NULL)
    {
        $version = $version ?? self::$default_api_version;
        return 'App\\Engines\\EngineV' . $version;
    }

    public static function getNewEngine($version = NULL)
    {
        $ClassName = self::getClassName($version);
        return new $ClassName();
    }

    public static function getEngineInstance($version = NULL)
    {
        $ClassName = self::getClassName($version);
        return $ClassName::getInstance();
    }

    public static function getFreshEngineInstance($version = NULL)
    {
        $ClassName = self::getClassName($version);
        return $ClassName::freshInstance();
    }

    public static function resetEngineInstance($version = NULL): void
    {
        $ClassName = self::getClassName($version);
        $ClassName::resetInstance();
    }
}