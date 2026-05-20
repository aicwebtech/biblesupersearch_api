<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Features\FeatureDefinitions;

class Feature extends Model
{
    protected $fillable = [
        'identifier',
        'language',
        'installed',
    ];

    protected $casts = [
        'installed' => 'boolean',
    ];

    protected static $is_enabled = [];

    /**
     * Synchronize features from definitions into the database
     * Creates or updates entries for all feature+language combinations
     * 
     * @return void
     */
    public static function syncFeatures(): void
    {
        foreach (FeatureDefinitions::all() as $definition) {
            $identifier = $definition['identifier'];
            $languages = $definition['languages'] ?? [null];

            // Ensure languages is an array (could be null or array)
            if ($languages === null) {
                $languages = [null];
            }

            foreach ($languages as $language) {
                self::firstOrCreate(
                    [
                        'identifier' => $identifier,
                        'language' => $language,
                    ],
                    [
                        'installed' => false,
                    ]
                );
            }
        }
    }

    /**
     * Install the feature by calling its install callback
     * 
     * @return bool
     */
    public function install(): bool
    {
        $definition = FeatureDefinitions::find($this->identifier);

        if (!$definition) {
            return false;
        }

        $callback = $definition['install'];
        $result = $callback($this->language);

        if ($result) {
            $this->update(['installed' => true]);
        }

        return $result;
    }

    /**
     * Uninstall the feature by calling its uninstall callback
     * 
     * @return bool
     */
    public function uninstall(): bool
    {
        $definition = FeatureDefinitions::find($this->identifier);

        if (!$definition) {
            return false;
        }

        $callback = $definition['uninstall'];
        $result = $callback($this->language);

        if ($result) {
            $this->update(['installed' => false]);
        }

        return $result;
    }

    public static function isEnabled(string $identifier, ?string $language = null): bool
    {
        $key = $identifier . ':' . ($language ?? 'null');

        if (array_key_exists($key, self::$is_enabled)) {
            return self::$is_enabled[$key];
        }

        $Feature = self::where('identifier', $identifier)
            ->where('language', $language)
            ->first();

        $enabled = $Feature ? $Feature->installed : false;
        self::$is_enabled[$key] = $enabled;

        return $enabled;
    }
}
