<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Features\FeatureDefinitions;

class Feature extends Model
{
    protected $fillable = [
        'code',
        'identifier',
        'language',
        'installed',
        'enabled',
    ];

    protected $casts = [
        'installed' => 'boolean',
        'enabled' => 'boolean',
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
            $languages = FeatureDefinitions::normalizeLanguages($definition);
            $mode = FeatureDefinitions::getLanguageMode($definition);
            
             // Ensure there's exactly one row for the feature-language (for global features or as a fallback)
            foreach ($languages as $language) {
                $code = self::buildCode($identifier, $language, $mode);
            
                self::firstOrCreate(
                    [
                        'code' => $code,
                    ],
                    [
                        'identifier' => $identifier,
                        'language' => $language,
                        'installed' => false,
                        'enabled' => false,
                    ]
                );
            }
        }

        self::clearEnabledCache();
    }

    /**
     * Install the feature by calling its install callback
     * 
     * @return bool
     */
    public function install(bool $enable = false): bool
    {
        $definition = FeatureDefinitions::find($this->identifier);

        if (!$definition) {
            return false;
        }

        $callback = $definition['install'];
        $result = $callback($this->language);

        if ($result) {
            $this->update([
                'installed' => true,
                'enabled' => $enable ? true : $this->enabled,
            ]);
        }

        self::clearEnabledCache();

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
            $this->update([
                'installed' => false,
                'enabled' => false,
            ]);
        }

        self::clearEnabledCache();

        return $result;
    }

    public function enable(): bool
    {
        if (!$this->installed) {
            return false;
        }

        $this->update(['enabled' => true]);
        self::clearEnabledCache();

        return true;
    }

    public function disable(): bool
    {
        $this->update(['enabled' => false]);
        self::clearEnabledCache();

        return true;
    }

    public static function isEnabled(string $code_or_identifier, ?string $language = null): bool
    {
        $key = $code_or_identifier . ':' . ($language ?? 'null');

        if (array_key_exists($key, self::$is_enabled)) {
            return self::$is_enabled[$key];
        }

        if($language) {
            $Feature = self::where('identifier', $code_or_identifier)
                ->where('language', $language)
                ->first();
        } else {
            $Feature = self::where('code', $code_or_identifier)
                ->first();

            if(!$Feature) {
                $Feature = self::where('identifier', $code_or_identifier)
                    ->whereNull('language')
                    ->first();
            }
        }

        $enabled = $Feature ? (bool)$Feature->enabled : false;
        self::$is_enabled[$key] = $enabled;

        return $enabled;
    }

    /**
     * Returns a flat map for statics output:
     * - {identifier}.global => bool
     * - {identifier}.{language} => bool (for language-specific rows)
     *
     * @return array<string, bool>
     */
    public static function isEnabledAll(): array
    {
        $map = ['_by_languages' => []];

        foreach (FeatureDefinitions::all() as $definition) {
            $identifier = $definition['identifier'];
            $mode = FeatureDefinitions::getLanguageMode($definition);
            $rows = self::where('identifier', $identifier)->get();
            $global = false;

            // For multi-language features, we want to know both the global state and the state for each language
            if($mode === FeatureDefinitions::LANGUAGE_MODE_MULTI) {
                foreach ($rows as $row) {
                    $isEnabled = (bool)$row->enabled;

                    if ($row->language) {
                        $map['_by_languages'][$identifier][$row->language] = $isEnabled;
                    }

                    $global = $global || $isEnabled;
                }

                $map[$identifier] = $global;
            } else {
                // There should only be one row ... but we'll loop just in case
                foreach ($rows as $row) {
                    $isEnabled = (bool)$row->enabled;                    
                    $map[$identifier] = $isEnabled;
                }
            }
        }

        return $map;
    }

    public static function buildCode(string $identifier, ?string $language, ?string $mode = null): string
    {
        $mode = $mode ?? FeatureDefinitions::LANGUAGE_MODE_NONE;

        if ($mode === FeatureDefinitions::LANGUAGE_MODE_MULTI && $language) {
            return $identifier . '___' . $language;
        }

        return $identifier;
    }

    public static function clearEnabledCache(): void
    {
        self::$is_enabled = [];
    }
}
