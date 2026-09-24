<?php

namespace App\Factories;

use App\Engine;

class EngineFactory
{
    protected static $default_api_version = 2;

    /**
     * The engine class serving the given API version.
     *
     * String concatenation, not a lookup: the version is the caller's to validate first - see
     * ApiController::versionedAction(), which 404s a version the application does not advertise
     * before the name reaches here.
     *
     * @param int|string|null $version
     * @return string
     */
    public static function getClassName(int|string|null $version = NULL): string
    {
        $version = $version ?? self::$default_api_version;
        return 'App\\Engines\\EngineV' . $version;
    }

    /**
     * @param int|string|null $version
     * @return Engine
     */
    public static function getNewEngine(int|string|null $version = NULL): Engine
    {
        $ClassName = self::getClassName($version);
        return new $ClassName();
    }

    /**
     * @param int|string|null $version
     * @return Engine
     */
    public static function getEngineInstance(int|string|null $version = NULL): Engine
    {
        $ClassName = self::getClassName($version);
        return $ClassName::getInstance();
    }

    /**
     * @param int|string|null $version
     * @return Engine
     */
    public static function getFreshEngineInstance(int|string|null $version = NULL): Engine
    {
        $ClassName = self::getClassName($version);
        return $ClassName::freshInstance();
    }

    /**
     * @param int|string|null $version
     * @return void
     */
    public static function resetEngineInstance(int|string|null $version = NULL): void
    {
        $ClassName = self::getClassName($version);
        $ClassName::resetInstance();
    }
}
