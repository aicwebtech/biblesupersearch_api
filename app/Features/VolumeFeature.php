<?php

namespace App\Features;

use App\Models\Volume;
use Illuminate\Database\Eloquent\Builder;

/**
 * Features page shortcuts for the volumes of one type in one language, such as the Strong's
 * dictionaries in English.  The feature's installed and enabled flags are derived from those
 * volumes rather than stored on their own.
 *
 * Written for any volume type, so other types can be offered on the Features page later.
 */
class VolumeFeature
{
    /**
     * Installed: any volume of the type and language is installed.
     * Enabled: any of them is enabled.
     *
     * @return array{installed: bool, enabled: bool}
     */
    public static function status(string $type, string $language): array
    {
        return [
            'installed' => static::query($type, $language)->where('installed', 1)->exists(),
            'enabled'   => static::query($type, $language)->where('installed', 1)->where('enabled', 1)->exists(),
        ];
    }

    /**
     * Installs the given modules of the type, creating a volume record from the module file for
     * any that has none.  Modules already installed are left installed, and enabled if $enable.
     *
     * When the type then has no usable default, the first listed module that is installed and
     * enabled becomes it: without one, a request that names no dictionary or language has
     * nothing to answer from.
     *
     * @param array<int, string> $modules
     * @throws FeatureException When a module cannot be found or installed
     */
    public static function install(string $type, string $language, array $modules, bool $enable): bool
    {
        if(!$modules) {
            throw new FeatureException('No ' . static::label($type) . ' volumes are listed for this language yet.');
        }

        $errors = [];
        $usable = [];

        foreach($modules as $module) {
            $Volume = Volume::findByTypeAndModule($type, $module) ?? Volume::createFromModuleFile($type, $module);

            if(!$Volume) {
                $errors[] = $module . ': no volume or module file found';
                continue;
            }

            if($Volume->language !== $language) {
                $errors[] = $module . ': is not in this language';
                continue;
            }

            if($Volume->installed) {
                if($enable && !$Volume->enabled) {
                    $Volume->enable();
                }
            }
            elseif(!$Volume->install(FALSE, $enable)) {
                $errors[] = $module . ': ' . implode('; ', $Volume->getErrors());
                continue;
            }

            $usable[] = $Volume;
        }

        static::ensureDefault($type, $usable);

        if($errors) {
            throw new FeatureException('Could not install: ' . implode(', ', $errors));
        }

        return TRUE;
    }

    /**
     * Uninstalls every installed volume of the type and language
     *
     * @throws FeatureException When one of them is the type's default, or could not be uninstalled
     */
    public static function uninstall(string $type, string $language): bool
    {
        static::guardDefault($type, $language, 'uninstall');

        $errors = [];

        foreach(static::query($type, $language)->where('installed', 1)->get() as $Volume) {
            if(!$Volume->uninstall()) {
                $errors[] = $Volume->module . ': ' . implode('; ', $Volume->getErrors());
            }
        }

        if($errors) {
            throw new FeatureException('Could not uninstall: ' . implode(', ', $errors));
        }

        return TRUE;
    }

    /**
     * Enables every installed volume of the type and language
     *
     * @throws FeatureException When none is installed
     */
    public static function enable(string $type, string $language): bool
    {
        $Volumes = static::query($type, $language)->where('installed', 1)->get();

        if($Volumes->isEmpty()) {
            throw new FeatureException('Nothing to enable: install it first.');
        }

        foreach($Volumes as $Volume) {
            $Volume->enabled || $Volume->enable();
        }

        return TRUE;
    }

    /**
     * Disables every volume of the type and language
     *
     * @throws FeatureException When one of them is the type's default
     */
    public static function disable(string $type, string $language): bool
    {
        static::guardDefault($type, $language, 'disable');

        foreach(static::query($type, $language)->where('enabled', 1)->get() as $Volume) {
            $Volume->disable();
        }

        return TRUE;
    }

    /**
     * Makes the first of the given volumes that is installed and enabled the type's default, if
     * the type has no usable default
     *
     * @param array<int, Volume> $Volumes
     */
    protected static function ensureDefault(string $type, array $Volumes): void
    {
        if(Volume::getDefault($type)) {
            return;
        }

        foreach($Volumes as $Volume) {
            if($Volume->installed && $Volume->enabled) {
                $Volume->makeDefault();
                return;
            }
        }
    }

    /**
     * Refuses an action that would take the type's default volume with it, as the Volumes page
     * does for that one volume
     *
     * @throws FeatureException
     */
    protected static function guardDefault(string $type, string $language, string $action): void
    {
        $Default = static::query($type, $language)->where('is_default', 1)->first();

        if($Default) {
            throw new FeatureException('Cannot ' . $action . ': ' . $Default->name . ' is the default ' . static::label($type)
                . '.  Make a volume in another language the default first.');
        }
    }

    /**
     * Volumes of the type in the language, through the type's own model
     */
    protected static function query(string $type, string $language): Builder
    {
        $class = Volume::getVolumeTypeClass($type);

        if(!$class) {
            throw new \InvalidArgumentException('Unknown volume type: ' . $type);
        }

        return $class::query()->where('language', $language);
    }

    protected static function label(string $type): string
    {
        return Volume::getType($type)['label'] ?? $type;
    }
}
