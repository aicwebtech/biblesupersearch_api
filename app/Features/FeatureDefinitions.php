<?php

namespace App\Features;

use App\Models\CrossReference;
use App\Models\StrongsDefinition;

class FeatureDefinitions
{
    public const LANGUAGE_MODE_NONE = 'none';
    public const LANGUAGE_MODE_SINGLE = 'single';
    public const LANGUAGE_MODE_MULTI = 'multi';

    /**
     * The Strong's dictionaries the Strong's Dictionaries feature installs, by language.  The
     * keys are the languages the feature is offered in.
     *
     * :todo - a stub: expand with the dictionaries each language should get.
     */
    public const STRONGS_PRESETS = [
        'en' => ['en_orig'],
        'es' => ['str_es'],
        'fr' => ['strong_fr'],
        'pt' => ['str_pt'],
        'ru' => ['strong_ru'],
        'zh' => ['cn_cbols'],
    ];

    /**
     * Get all feature definitions
     *
     * Each definition holds:
     *  identifier, name, description ({language} is replaced), languages (NULL, a code, or a list)
     *  install   - fn(?string $language, bool $enable): bool
     *  uninstall - fn(?string $language): bool
     *  enable, disable (optional) - fn(?string $language): bool, run before the flag is changed
     *  derived (optional) - fn(?string $language): array{installed: bool, enabled: bool}; the
     *      feature's flags come from this rather than from its own actions
     *
     * A callback that refuses or fails throws FeatureException with a message for the admin.
     *
     * @return array
     */
    public static function all(): array
    {
        return [
            [
                'identifier' => 'cross_references',
                'name' => 'Cross References',
                'description' => 'Cross references data',
                'languages' => null,
                'install' => function(?string $language, bool $enable = false): bool {
                    CrossReference::migrateFromCsv();
                    return true;
                },
                'uninstall' => function(?string $language): bool {
                    CrossReference::truncate();
                    return true;
                },
            ],
            static::volumeDefinition('strongs', "Strong's Dictionaries", "Strong's dictionaries in {language}", static::STRONGS_PRESETS),
            [
                'identifier' => 'strongs_legacy',
                'name' => "Strong's Definitions (Legacy)",
                'description' => "The original Strong's definitions table, used by API v2",
                'languages' => 'en',
                'install' => function(?string $language, bool $enable = false): bool {
                    StrongsDefinition::truncate();
                    StrongsDefinition::migrateFromCsv();
                    return true;
                },
                'uninstall' => function(?string $language): bool {
                    StrongsDefinition::truncate();
                    return true;
                },
            ],
        ];
    }

    /**
     * A feature over the volumes of one type, one row per language: see VolumeFeature
     *
     * @param array<string, array<int, string>> $presets Modules installed per language
     * @return array
     */
    public static function volumeDefinition(string $type, string $name, string $description, array $presets): array
    {
        return [
            'identifier' => $type,
            'name' => $name,
            'description' => $description,
            'languages' => array_keys($presets),
            'install' => function(?string $language, bool $enable = false) use ($type, $presets): bool {
                return VolumeFeature::install($type, (string) $language, $presets[$language] ?? [], $enable);
            },
            'uninstall' => function(?string $language) use ($type): bool {
                return VolumeFeature::uninstall($type, (string) $language);
            },
            'enable' => function(?string $language) use ($type): bool {
                return VolumeFeature::enable($type, (string) $language);
            },
            'disable' => function(?string $language) use ($type): bool {
                return VolumeFeature::disable($type, (string) $language);
            },
            'derived' => function(?string $language) use ($type): array {
                return VolumeFeature::status($type, (string) $language);
            },
        ];
    }

    public static function getLanguageMode(array $definition): string
    {
        $languages = $definition['languages'] ?? null;

        if (is_array($languages)) {
            if (empty($languages)) {
                return self::LANGUAGE_MODE_NONE;
            }

            return self::LANGUAGE_MODE_MULTI;
        }

        if (is_string($languages) && trim($languages) !== '') {
            return self::LANGUAGE_MODE_SINGLE;
        }

        if ($languages) {
            return self::LANGUAGE_MODE_SINGLE;
        }

        return self::LANGUAGE_MODE_NONE;
    }

    /**
     * Normalize languages to explicit rows for DB sync.
     *
     * @return array<int, string|null>
     */
    public static function normalizeLanguages(array $definition): array
    {
        $mode = self::getLanguageMode($definition);
        $languages = $definition['languages'] ?? null;

        if ($mode === self::LANGUAGE_MODE_NONE) {
            return [null];
        }

        if ($mode === self::LANGUAGE_MODE_SINGLE) {
            return [is_string($languages) ? trim($languages) : (string)$languages];
        }

        $normalized = [];

        foreach ($languages as $language) {
            $value = is_string($language) ? trim($language) : (string)$language;

            if ($value === '') {
                continue;
            }

            $normalized[] = $value;
        }

        return empty($normalized) ? [null] : $normalized;
    }

    /**
     * Get a specific feature definition by identifier
     * 
     * @param string $identifier
     * @return array|null
     */
    public static function find(string $identifier): ?array
    {
        foreach (self::all() as $definition) {
            if ($definition['identifier'] === $identifier) {
                return $definition;
            }
        }
        return null;
    }
}
