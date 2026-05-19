<?php

namespace App\Features;

class FeatureDefinitions
{
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
                    // TODO: Implement install callback for cross references
                    return true;
                },
                'uninstall' => function(?string $language): bool {
                    // TODO: Implement uninstall callback for cross references
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
