<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Features\FeatureDefinitions;
use App\Features\FeatureException;
use App\Traits\Error;

class Feature extends Model
{
    use Error;

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

        static::refreshDerived();
        self::clearEnabledCache();
    }

    /**
     * Writes the live flags of every derived feature (one whose state comes from elsewhere, such
     * as its volumes) into its row, so the grid can filter and sort on them
     *
     * @return void
     */
    public static function refreshDerived(): void
    {
        foreach (FeatureDefinitions::all() as $definition) {
            if (!isset($definition['derived'])) {
                continue;
            }

            foreach (self::where('identifier', $definition['identifier'])->get() as $Feature) {
                $Feature->applyDerived($definition);
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
        $definition = FeatureDefinitions::find((string) $this->identifier);

        if (!$definition) {
            return $this->addError('This feature is no longer defined', 4);
        }

        $result = $this->runCallback($definition['install'], $enable);

        if ($result) {
            $this->update([
                'installed' => true,
                'enabled' => $enable ? true : $this->enabled,
            ]);
        }

        $this->applyDerived($definition);
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
        $definition = FeatureDefinitions::find((string) $this->identifier);

        if (!$definition) {
            return $this->addError('This feature is no longer defined', 4);
        }

        $result = $this->runCallback($definition['uninstall']);

        if ($result) {
            $this->update([
                'installed' => false,
                'enabled' => false,
            ]);
        }

        $this->applyDerived($definition);
        self::clearEnabledCache();

        return $result;
    }

    public function enable(): bool
    {
        $definition = FeatureDefinitions::find((string) $this->identifier);

        // A derived feature's callback decides; its own installed flag may be stale
        if (!$this->installed && !isset($definition['derived'])) {
            return $this->addError('Feature must be installed before it can be enabled.', 4);
        }

        if (isset($definition['enable']) && !$this->runCallback($definition['enable'])) {
            return false;
        }

        $this->update(['enabled' => true]);
        $definition && $this->applyDerived($definition);
        self::clearEnabledCache();

        return true;
    }

    public function disable(): bool
    {
        $definition = FeatureDefinitions::find((string) $this->identifier);

        if (isset($definition['disable']) && !$this->runCallback($definition['disable'])) {
            return false;
        }

        $this->update(['enabled' => false]);
        $definition && $this->applyDerived($definition);
        self::clearEnabledCache();

        return true;
    }

    /**
     * Runs one of a feature's 'all' actions (FeatureDefinitions::ALL_ACTIONS) over every
     * language at once, then refreshes the flags of its rows
     *
     * @param string $identifier Feature identifier
     * @param string $action install, enable, disable or uninstall
     * @param bool $enable Install action: enable too
     * @return array<int, string> Errors; empty on success
     */
    public static function runAll(string $identifier, string $action, bool $enable = false): array
    {
        $definition = FeatureDefinitions::find($identifier);

        if (!$definition) {
            return ['This feature is not defined.'];
        }

        if (!in_array($action, FeatureDefinitions::ALL_ACTIONS, true) || !isset($definition['all'][$action])) {
            return ['This feature does not support ' . $action . ' all.'];
        }

        return $action === 'install'
            ? static::runAllCallback($definition['all'][$action], $enable)
            : static::runAllCallback($definition['all'][$action]);
    }

    /**
     * What one of a feature's 'all' actions would lose for good, such as volumes with no module
     * file to install them again from, so the admin can be asked first
     *
     * @param string $identifier Feature identifier
     * @param string $action install, enable, disable or uninstall
     * @return array<int, string> Names of what would be lost; empty when nothing would be
     */
    public static function allLosses(string $identifier, string $action): array
    {
        $callback = FeatureDefinitions::find($identifier)['all_losses'][$action] ?? null;

        return $callback ? $callback() : [];
    }

    /**
     * Runs an 'all' callback for runAll().  Any failure becomes an error rather than a 500, and
     * the rows' flags are refreshed whatever happened, so what got done before it shows.
     *
     * @return array<int, string> Errors; empty on success
     */
    protected static function runAllCallback(callable $callback, mixed ...$args): array
    {
        $errors = [];

        try {
            $callback(...$args);
        }
        catch (FeatureException $e) {
            $errors[] = $e->getMessage();
        }
        catch (\Throwable $e) {
            // Logged in full; the message itself may carry database details
            report($e);
            $errors[] = 'An unexpected error occurred. Details are in the error log.';
        }
        finally {
            // Whatever got done before a failure must show on the grid
            static::refreshDerived();
        }

        return $errors;
    }

    /**
     * Runs a definition callback for this feature's language.  A FeatureException becomes an
     * error on this feature; anything else propagates.
     */
    protected function runCallback(callable $callback, mixed ...$args): bool
    {
        try {
            return (bool) $callback($this->language, ...$args);
        }
        catch (FeatureException $e) {
            return $this->addError($e->getMessage(), 4);
        }
    }

    /**
     * Overwrites the stored flags with the derived ones, for a definition that has them
     */
    protected function applyDerived(array $definition): void
    {
        if (!isset($definition['derived'])) {
            return;
        }

        $status = $definition['derived']($this->language);

        $this->update([
            'installed' => (bool) $status['installed'],
            'enabled' => (bool) $status['enabled'],
        ]);
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
     * For each feature definition, determine if it's enabled globally (any language) and for each language (if multi-language)
     * 
     * Returns a map of:
     * - {identifier} => bool (global true if any language row is enabled for multi-language features)
     * - _by_languages[{identifier}][{language}] => bool (only for LANGUAGE_MODE_MULTI)
     *
     * @return array<string, mixed>
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
