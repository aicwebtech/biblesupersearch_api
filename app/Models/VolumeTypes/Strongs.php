<?php

namespace App\Models\VolumeTypes;

use App\Models\Bible;
use App\Models\Language;
use App\Models\Volume;
use App\Models\VolumeAttr;
use Illuminate\Support\Collection;

class Strongs extends VolumeTypeBase
{
    protected static $type = 'strongs';

    /**
     * The import classes for each import source.
     * Sources without a class are not importable for this type
     *
     * @var array<string, string>
     */
    public const IMPORT_CLASSES = [
        'csv'   => \App\ContentImporters\Csv\CsvStrongs::class,
        'excel' => \App\ContentImporters\Excel\ExcelStrongs::class,
    ];

    /**
     * language_attr attribute holding a language's default Strong's dictionary (a module name)
     */
    public const LANGUAGE_ATTR = 'strongs_dictionary';

    /**
     * volumes_attr attribute holding a Bible's default Strong's dictionary (a module name), under
     * type VolumeAttr::TYPE_BIBLE and the Bible's module
     */
    public const BIBLE_ATTR = 'default_strongs_dictionary';

    /**
     * The dictionary a new installation gets as the Strong's default, and the one made the
     * default again when there is none (hardDefaultModule())
     */
    public const DEFAULT_MODULE = 'en_orig';

    /**
     * info.json columns for a Strong's dictionary - see VolumeTypeBase::INFO_FIELDS
     */
    public const INFO_FIELDS = [
        'type', 'module', 'official', 'name', 'shortname', 'language', 'year', 'publisher', 'owner',
        'url', 'copyright', 'copyright_id', 'copyright_statement', 'description', 'module_version',
    ];

    public static function hardDefaultModule(): ?string
    {
        return static::DEFAULT_MODULE;
    }

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
     * Whether any Strong's dictionary is installed and enabled
     */
    public static function hasAvailable(): bool
    {
        return static::where('installed', 1)
            ->where('enabled', 1)
            ->exists();
    }

    /**
     * The dictionary to use when none was asked for.  In order, the first that is usable:
     *  - the Bible's default dictionary (BIBLE_ATTR), when a Bible is given
     *  - the default of the Bible's language, when a Bible is given
     *  - the default of $language (the user interface language)
     *  - the global default
     *
     * A Bible or language default that is not installed and enabled is skipped rather than
     * reported, as the caller did not ask for it by name.
     *
     * @param string|null $language Language code of the user interface
     * @param Bible|null $Bible Bible the definitions are for
     * @return static|null NULL when no dictionary is usable
     */
    public static function resolveDefault(?string $language, ?Bible $Bible = NULL): ?static
    {
        if($Bible) {
            $module = VolumeAttr::getAttr(VolumeAttr::TYPE_BIBLE, (string) $Bible->module, static::BIBLE_ATTR);
            $Volume = $module ? static::findAvailable($module) : NULL;

            if($Volume) {
                return $Volume;
            }

            $Volume = static::findLanguageDefault(is_string($Bible->lang_short) ? Language::baseLanguageCode($Bible->lang_short) : NULL);

            if($Volume) {
                return $Volume;
            }
        }

        return static::findLanguageDefault($language) ?? static::getDefault(static::$type);
    }

    /**
     * A language's default dictionary, if it has one that is installed and enabled
     */
    protected static function findLanguageDefault(?string $language): ?static
    {
        if(!$language) {
            return NULL;
        }

        $module = Language::getLanguageAttr($language, static::LANGUAGE_ATTR);

        return $module ? static::findAvailable($module) : NULL;
    }

    /**
     * Validation rules for a default dictionary chosen in an admin form (a language's or a
     * Bible's): empty, or the module of an installed, enabled dictionary.
     *
     * The value already stored is accepted even if that dictionary has since been disabled or
     * uninstalled: the edit form sends it back with every save, and refusing it would block any
     * change to the record.  The API skips an unusable default anyway.
     *
     * @param string|null $stored Value stored before this save
     * @return array<int, string|\Closure>
     */
    public static function selectionRules(?string $stored): array
    {
        return [
            'bail',
            'nullable',
            'string',
            function(string $attribute, mixed $value, \Closure $fail) use ($stored): void {
                if(!is_string($value) || $value === $stored) {
                    return;
                }

                if(!static::findAvailable($value)) {
                    $fail('The selected Strong\'s dictionary is not installed and enabled.');
                }
            },
        ];
    }

    /**
     * Touches the given dictionaries' updated_at.  language_attr and volumes_attr have no
     * timestamps, so a changed language or Bible default touches the dictionaries it moved
     * between instead: statics_changed reports volumes.updated_at, and Strong's resolution
     * depends on these defaults.
     */
    public static function touchModules(?string $old, ?string $new): void
    {
        if($old === $new) {
            return;
        }

        Volume::where('type', static::$type)
            ->whereIn('module', array_filter([$old, $new], fn($module) => $module !== NULL))
            ->update(['updated_at' => now()]);
    }
}
