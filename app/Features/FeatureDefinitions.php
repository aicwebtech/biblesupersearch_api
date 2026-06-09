<?php

namespace App\Features;

use App\Models\CrossReference;

class FeatureDefinitions
{
    public const LANGUAGE_MODE_NONE = 'none';
    public const LANGUAGE_MODE_SINGLE = 'single';
    public const LANGUAGE_MODE_MULTI = 'multi';

    /**
     * Get all feature definitions
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
                'install' => function(?string $language): bool {
                    CrossReference::migrateFromCsv();
                    return true;
                },
                'uninstall' => function(?string $language): bool {
                    CrossReference::truncate();
                    return true;
                },
            ],
            [
                'identifier' => 'strongs',
                'name' => "Strong's Definitions",
                'description' => "Strong's definitions in {language}",
                'languages' => ['en', 'ru', 'es'],
                'install' => function(?string $language): bool {
                    // TODO: Implement install callback for Strong's definitions
                    return true;
                },
                'uninstall' => function(?string $language): bool {
                    // TODO: Implement uninstall callback for Strong's definitions
                    return true;
                },
            ],
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
