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

        return static::preferringHardDefault($type, function() use ($type, $language, $modules, $enable): bool {
            return static::installModules($type, $language, $modules, $enable);
        });
    }

    /**
     * install(), without the hard default preference
     *
     * @param array<int, string> $modules
     * @throws FeatureException When a module cannot be found or installed
     */
    protected static function installModules(string $type, string $language, array $modules, bool $enable): bool
    {
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
                if($enable && !$Volume->enabled && !$Volume->enable()) {
                    $errors[] = $module . ': ' . implode('; ', $Volume->getErrors());
                    continue;
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

        $errors = static::uninstallVolumes(static::query($type, $language)->where('installed', 1)->get());

        if($errors) {
            throw new FeatureException('Could not uninstall: ' . implode(', ', $errors));
        }

        return TRUE;
    }

    /**
     * Enables every installed volume of the type and language that has content
     *
     * @throws FeatureException When none is installed, or one could not be enabled
     */
    public static function enable(string $type, string $language): bool
    {
        return static::enableInstalled($type, static::query($type, $language));
    }

    /**
     * Installs the presets of every language of the type: see install()
     *
     * A language that fails, for whatever reason, doesn't stop the rest.
     *
     * @param array<string, array<int, string>> $presets Modules per language
     * @throws FeatureException When any language could not be installed; the others still are
     */
    public static function installAll(string $type, array $presets, bool $enable): bool
    {
        return static::preferringHardDefault($type, fn(): bool => static::installPresets($type, $presets, $enable));
    }

    /**
     * installAll(), without the hard default preference
     *
     * @param array<string, array<int, string>> $presets Modules per language
     * @throws FeatureException When any language could not be installed; the others still are
     */
    protected static function installPresets(string $type, array $presets, bool $enable): bool
    {
        $errors = [];

        foreach($presets as $language => $modules) {
            try {
                static::install($type, (string) $language, $modules, $enable);
            }
            catch(FeatureException $e) {
                $errors[] = $e->getMessage();
            }
            catch(\Throwable $e) {
                // Logged in full; the message itself may carry database details
                report($e);
                $errors[] = $language . ': an unexpected error occurred. Details are in the error log.';
            }
        }

        if($errors) {
            throw new FeatureException(implode(' ', $errors));
        }

        return TRUE;
    }

    /**
     * Enables every installed volume of the type, in any language, that has content
     *
     * @throws FeatureException When none is installed, or one could not be enabled
     */
    public static function enableAll(string $type): bool
    {
        return static::enableInstalled($type, static::typeQuery($type));
    }

    /**
     * Enables the installed volumes the query selects: enable() for a language, enableAll() for
     * the whole type
     *
     * @throws FeatureException When none is installed, or one could not be enabled
     */
    protected static function enableInstalled(string $type, Builder $Query): bool
    {
        return static::preferringHardDefault($type, fn(): bool => static::enableQueried($Query));
    }

    /**
     * enableInstalled(), without the hard default preference
     *
     * @throws FeatureException When none is installed, or one could not be enabled
     */
    protected static function enableQueried(Builder $Query): bool
    {
        $Volumes = $Query->where('installed', 1)->get();

        if($Volumes->isEmpty()) {
            throw new FeatureException('Nothing to enable: install it first.');
        }

        $errors = static::enableVolumes($Volumes);

        if($errors) {
            throw new FeatureException('Could not enable: ' . implode(', ', $errors));
        }

        return TRUE;
    }

    /**
     * Disables every enabled volume of the type, in any language, default included: unlike
     * disable() this takes the type out of use entirely, so the default loses its flag, as
     * with uninstallAll().  When enabled again the type's hard default
     * (VolumeTypeBase::hardDefaultModule()) becomes the default if it is among them, otherwise
     * the first volume enabled (Volume::claimDefaultIfNone()).
     *
     * A disabled volume keeping the flag would still block disabling or uninstalling it
     * (guardDefault(), VolumeController::defaultGuard()) while serving nothing.
     */
    public static function disableAll(string $type): bool
    {
        static::disableVolumes(static::typeQuery($type)->where(fn(Builder $Query) => $Query->where('enabled', 1)->orWhere('is_default', 1))->get());

        return TRUE;
    }

    /**
     * Disables the volumes, and takes the default flag off any of them that has it: a disabled
     * default serves nothing.  disable() reaches here only after guardDefault(), so with none.
     *
     * @param iterable<int, Volume> $Volumes
     */
    protected static function disableVolumes(iterable $Volumes): void
    {
        foreach($Volumes as $Volume) {
            if($Volume->is_default) {
                $Volume->is_default = 0;
            }

            if($Volume->enabled) {
                $Volume->disable();
            }
            elseif($Volume->isDirty()) {
                $Volume->save();
            }
        }
    }

    /**
     * Uninstalls every installed volume of the type, in any language, default included: unlike
     * uninstall() this takes the type out of use entirely, so there is no default to protect.
     * The volume records and module files are kept, to be installed again.
     *
     * The default goes last, and only when every other volume was uninstalled, then loses its
     * default flag: a failure part way never leaves the remaining volumes without a default.
     *
     * @throws FeatureException When any could not be uninstalled
     */
    public static function uninstallAll(string $type): bool
    {
        $errors = static::uninstallVolumes(static::typeQuery($type)->where('installed', 1)->get());

        if($errors) {
            throw new FeatureException('Could not uninstall: ' . implode(', ', $errors));
        }

        return TRUE;
    }

    /**
     * Uninstalls the volumes, default last, as uninstallAll() describes
     *
     * @param iterable<int, Volume> $Volumes
     * @return array<int, string> Errors, one per volume left installed
     */
    protected static function uninstallVolumes(iterable $Volumes): array
    {
        $errors  = [];
        $Volumes = collect($Volumes)->sortBy(fn(Volume $Volume) => (int) $Volume->is_default)->values();

        foreach($Volumes as $Volume) {
            // Keep the default for the volumes that could not be uninstalled
            if($Volume->is_default && $errors) {
                $errors[] = $Volume->module . ': left installed as the default';
                continue;
            }

            if(!$Volume->uninstall()) {
                $errors[] = $Volume->module . ': ' . implode('; ', $Volume->getErrors());
                continue;
            }

            if($Volume->is_default) {
                $Volume->is_default = 0;
                $Volume->save();
            }
        }

        return $errors;
    }

    /**
     * Enables each of the volumes not already enabled
     *
     * @param iterable<int, Volume> $Volumes
     * @return array<int, string> Errors, one per volume that could not be enabled
     */
    protected static function enableVolumes(iterable $Volumes): array
    {
        $errors = [];

        // The default first: one flagged but disabled (outside the admin pages; disableAll()
        // and uninstallAll() clear the flag) is enabled before another volume can claim the
        // default (Volume::claimDefaultIfNone())
        $Volumes = collect($Volumes)->sortByDesc(fn(Volume $Volume) => (int) $Volume->is_default)->values();

        foreach($Volumes as $Volume) {
            if(!$Volume->enabled && !$Volume->enable()) {
                $errors[] = $Volume->module . ': ' . implode('; ', $Volume->getErrors());
            }
        }

        return $errors;
    }

    /**
     * Disables every volume of the type and language
     *
     * @throws FeatureException When one of them is the type's default
     */
    public static function disable(string $type, string $language): bool
    {
        static::guardDefault($type, $language, 'disable');
        static::disableVolumes(static::query($type, $language)->where('enabled', 1)->get());

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
     * Volumes uninstallAll() would lose for good: installed, but with no module file to install
     * them again from, such as a dictionary imported from a CSV or Excel file.  Uninstalling
     * drops the content table, and Volume::install() has nothing to rebuild it from.
     *
     * @return array<int, string> Their names, with the module
     */
    public static function uninstallAllLosses(string $type): array
    {
        return static::typeQuery($type)->where('installed', 1)->orderBy('name')->get()
            ->reject(fn(Volume $Volume): bool => $Volume->hasModuleFile())
            ->map(fn(Volume $Volume): string => $Volume->name . ' (' . $Volume->module . ')')
            ->values()
            ->all();
    }

    /**
     * Runs an install or enable action and, when the type had no default flagged before it,
     * makes the type's hard default (VolumeTypeBase::hardDefaultModule()) the default if the
     * action left it installed and enabled.
     *
     * Without this, whichever volume happens to be enabled first claims the default
     * (Volume::claimDefaultIfNone()): after Disable All, Enable All would hand it to the
     * lowest id rather than the dictionary a new installation starts with.  A default already
     * flagged, even a disabled one about to be enabled, is never replaced.
     *
     * @param callable(): bool $action
     */
    protected static function preferringHardDefault(string $type, callable $action): bool
    {
        $module    = static::hardDefaultModule($type);
        $unflagged = $module && !static::typeQuery($type)->where('is_default', 1)->exists();

        try {
            return $action();
        }
        finally {
            if($unflagged) {
                $Hard = static::typeQuery($type)->where('module', $module)->first();

                if($Hard && $Hard->installed && $Hard->enabled && !$Hard->is_default) {
                    $Hard->makeDefault();
                }
            }
        }
    }

    /**
     * The type's hard default module (VolumeTypeBase::hardDefaultModule())
     */
    protected static function hardDefaultModule(string $type): ?string
    {
        $class = Volume::getVolumeTypeClass($type);

        return $class ? $class::hardDefaultModule() : NULL;
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
        return static::typeQuery($type)->where('language', $language);
    }

    /**
     * Volumes of the type, in any language, through the type's own model
     */
    protected static function typeQuery(string $type): Builder
    {
        $class = Volume::getVolumeTypeClass($type);

        if(!$class) {
            throw new \InvalidArgumentException('Unknown volume type: ' . $type);
        }

        return $class::query();
    }

    protected static function label(string $type): string
    {
        return Volume::getType($type)['label'] ?? $type;
    }
}
