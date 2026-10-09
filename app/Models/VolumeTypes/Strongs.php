<?php

namespace App\Models\VolumeTypes;

use App\Models\Language;
use Illuminate\Support\Collection;

class Strongs extends VolumeTypeBase
{
    protected static $type = 'strongs';

    /**
     * language_attr attribute holding a language's default Strong's dictionary (a module name)
     */
    public const LANGUAGE_ATTR = 'strongs_dictionary';

    /**
     * info.json columns for a Strong's dictionary - see VolumeTypeBase::INFO_FIELDS
     */
    public const INFO_FIELDS = [
        'type', 'module', 'official', 'name', 'shortname', 'language', 'year', 'publisher', 'owner',
        'url', 'copyright', 'copyright_id', 'copyright_statement', 'description', 'module_version',
    ];

    /**
     * Strong's dictionaries that can be used: installed and enabled
     *
     * @return Collection<int, static>
     */
    public static function availableDictionaries(): Collection
    {
        return static::where('installed', 1)
            ->where('enabled', 1)
            ->orderBy('name')
            ->get(['id', 'module', 'name', 'shortname', 'language']);
    }

    /**
     * An installed, enabled Strong's dictionary by module name
     *
     * @param string $module
     * @return static|null
     */
    public static function findAvailable(string $module): ?static
    {
        return static::where('module', $module)
            ->where('installed', 1)
            ->where('enabled', 1)
            ->first();
    }

    /**
     * The dictionary to use when none was asked for: the language's default if it is usable,
     * otherwise the global default.
     *
     * A language default that is not installed and enabled is skipped rather than reported, as
     * the caller did not ask for it by name.
     *
     * @param string|null $language Language code
     * @return static|null NULL when no dictionary is usable
     */
    public static function resolveDefault(?string $language): ?static
    {
        if($language) {
            $module = Language::getLanguageAttr($language, static::LANGUAGE_ATTR);
            $Volume = $module ? static::findAvailable($module) : NULL;

            if($Volume) {
                return $Volume;
            }
        }

        return static::getDefault(static::$type);
    }
}
