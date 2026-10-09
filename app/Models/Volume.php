<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Traits\Error;
use App\Helpers;
use App\Models\VolumeContent\ContentInterface;
use App\Models\VolumeContent\ContentBase;
use App\Models\VolumeTypes\VolumeTypeBase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use ZipArchive;

/**
 * A volume of extra-Biblical content, such as a Strong's dictionary.
 *
 * This is the meta record, the counterpart of Bible.  The content itself varies by type and
 * lives in its own per-module table (BSS-152 phase 2).
 */
class Volume extends Model
{
    use Error;

    /**
     * Registered volume types, as type => settings
     *
     * Settings:
     *  label - display name of the type
     *  volume_class - the class representing the customized volume model (extends this class)
     *  content_class - the class representing the content model (implements ContentInterface)
     */
    public const TYPES = [
        'strongs' => [
            'label'         => 'Strong\'s Dictionary',
            'volume_class'  => \App\Models\VolumeTypes\Strongs::class,
            'content_class' => \App\Models\VolumeContent\StrongsContent::class,
        ],
    ];

    /**
     * Once set, these cannot be changed, as the content table is derived from them
     */
    public const IMMUTABLE_FIELDS = ['type', 'module'];

    /**
     * MySQL's maximum identifier length, which content table names must fit within
     */
    public const MAX_TABLE_NAME_LENGTH = 64;

    /**
     * info.json fields that identify where a module came from rather than describe it.  Never
     * taken from a file: type and module come from the file name, official from its directory,
     * and module_version is handled on its own (see export() and updateModule()).
     */
    public const PROVENANCE_FIELDS = ['type', 'module', 'official', 'module_version'];

    static protected $module_invalid_reason = null;

    protected $fillable = [
        'type',
        'name',
        'shortname',
        'language',
        'module',
        'year',
        'description',
        'copyright',
        'copyright_id',
        'copyright_statement',
        'url',
        'italics',
        'strongs',
        'red_letter',
        'rank',
        'official',
        'research',
        'restrict',
        'publisher',
        'hebrew_text_id',
        'greek_text_id',
        'translation_type_id',
        'owner',
        'citation_limit',
        'module_version',
        'importer',
        'audio_enable',
        'tts_enable',
        'tts_api',
        'tts_voice',
        'tts_speed',
        'audio_structure',
        'import_file',
    ];

    protected $attributes = [
        'copyright_id'  => NULL,
        'rank'          => 1000,
    ];

    public $Content = null;

    /**
     * @return array<string, array{label: string}>
     */
    public static function getTypes(): array
    {
        return static::TYPES;
    }

    /**
     * Settings for a single type, or NULL if the type is not registered
     *
     * @return array{label: string}|null
     */
    public static function getType(string $type): ?array
    {
        return static::TYPES[$type] ?? NULL;
    }

    /**
     * Validation rules for creating or updating a volume
     *
     * Items in this list also need to be in $fillable or mass assignment will fail
     *
     * @param int|null $volume_id
     * @param string|null $type Type of the volume; name, shortname and module are unique per type
     * @return array<string, mixed>
     */
    public static function getUpdateRules(?int $volume_id = NULL, ?string $type = NULL): array
    {
        $volume_id = (int) $volume_id;

        $unique = fn() => Rule::unique('volumes')->where('type', (string) $type)->ignore($volume_id);

        return [
            'type'      => ['required', Rule::in(array_keys(static::getTypes()))],
            'name'      => ['required', 'max:255', $unique()],
            'shortname' => ['required', 'max:255', $unique()],
            'module'    => [
                'required',
                'max:50',
                $unique(),
                function($attribute, $value, $fail) use ($type) {
                    if(!static::validateModule($value)) {
                        $fail('Module name is invalid: ' . static::$module_invalid_reason);
                        return;
                    }

                    $db_prefix = (new static())->getConnection()->getTablePrefix();

                    if($type && static::contentTableNameTooLong($type, $value, $db_prefix)) {
                        $fail('Module name is too long for this volume type');
                    }
                },
            ],
            'language'              => 'required|alpha_dash|min:2|max:10',
            'year'                  => 'nullable|max:255',
            'rank'                  => 'sometimes|required|int',
            'owner'                 => 'nullable|max:100',
            'publisher'             => 'nullable|max:100',
            'url'                   => 'nullable|max:255',
            'description'           => 'nullable',
            'copyright_statement'   => 'nullable',
            'copyright_id'          => 'required|integer',
        ];
    }

    /**
     * Validates a module name
     *
     * Same checks as Bible::validateModule(), less the PHP reserved word check: no PHP class is
     * generated from a volume module name.  It does become part of a DB table name (phase 2),
     * hence the restricted character set.
     *
     * @param mixed $module
     * @return bool
     */
    public static function validateModule(mixed $module): bool
    {
        if(!is_string($module) || $module === '') {
            static::$module_invalid_reason = 'Module name is empty';
            return FALSE;
        }

        if(preg_match('/[^a-z_0-9]/', $module)) {
            static::$module_invalid_reason = 'Module name contains invalid characters';
            return FALSE;
        }

        if(!preg_match('/^[a-z]{2}/', $module)) {
            static::$module_invalid_reason = 'Module name must start with at least two letters';
            return FALSE;
        }

        return TRUE;
    }

    /**
     * :todo - not finished
     * Mimic a DB relationship
     * 'One to TABLE' relationship
     * Each volume record points to an entire DB table
     * Future note: When implementing for Bible, this will just call verses()
     */
    public function content($force = FALSE): ContentInterface
    {
        if (!$this->module) {
            throw new \Exception('Module required on model to access contents model');
        }

        if(!$this->type) {
            throw new \Exception('Type required on model to access contents model');
        }

        if (!$this->Content || $force) {
            $class_name = self::getContentClassName($this->type);

            if(!$class_name) {
                throw new \Exception('Content class not defined for volume type: ' . $this->type);
            }

            if(!class_exists($class_name)) {
                throw new \Exception('Content class does not exist: ' . $class_name);
            }

            $this->Content = new $class_name();
            $this->Content->setVolume($this); // This circular reference may be a bad thing
            $this->Content->setModule($this->module);
        }

        return $this->Content;
    }

    public static function getModuleInvalidReason(): ?string
    {
        return static::$module_invalid_reason;
    }

    public static function findByTypeAndModule(string $type, string $module): ?static
    {
        return static::where('type', $type)->where('module', $module)->first();
    }

    /**
     * Whether the content table name for this type and module would exceed MySQL's identifier
     * limit, once the connection's table prefix is added.
     *
     * The module max:50 rule alone cannot guarantee this: the content table name also carries
     * the DB prefix and a per-type prefix, both of which vary.
     *
     * @param string $type
     * @param string $module
     * @param string $db_prefix The connection's table prefix
     * @return bool FALSE for an unregistered type; the type rule reports that
     */
    public static function contentTableNameTooLong(string $type, string $module, string $db_prefix = ''): bool
    {
        $class_name = static::getContentClassName($type);

        if(!$class_name) {
            return FALSE;
        }

        return strlen($db_prefix . $class_name::getContentTableName($type, $module)) > static::MAX_TABLE_NAME_LENGTH;
    }

    public static function getContentClassName(string $type): ?string
    {
        return static::TYPES[$type]['content_class'] ?? NULL;
    }

    // -----------------------------------------------------------------------
    // Module files
    // -----------------------------------------------------------------------

    /**
     * volumes columns a module of this type carries in its info.json
     *
     * @param string $type
     * @return array<int, string>
     */
    public static function getInfoFields(string $type): array
    {
        $class = static::TYPES[$type]['volume_class'] ?? NULL;

        return ($class && defined($class . '::INFO_FIELDS')) ? $class::INFO_FIELDS : VolumeTypeBase::INFO_FIELDS;
    }

    /**
     * The settings a module file carries: what Export Meta writes and Revert, Update and creating
     * a volume from a file apply.  INFO_FIELDS less the provenance fields.
     *
     * @param string $type
     * @return array<int, string>
     */
    public static function getSettingsFields(string $type): array
    {
        return array_values(array_diff(static::getInfoFields($type), static::PROVENANCE_FIELDS));
    }

    /**
     * Directory of official modules of a type: content/<type>/
     *
     * Built from this file's location, not base_path(), so it works without the application.
     *
     * @param string $type
     * @param bool $short Relative to the project root, for messages
     * @return string
     */
    public static function getModulePath(string $type, bool $short = FALSE): string
    {
        if(!preg_match('/^[a-z][a-z_]*$/', $type)) {
            throw new \InvalidArgumentException('Invalid volume type: ' . $type);
        }

        return ($short ? 'content/' : dirname(__DIR__, 2) . '/content/') . $type . '/';
    }

    /**
     * Directory of unofficial modules of a type: content/<type>/unofficial/
     */
    public static function getUnofficialModulePath(string $type, bool $short = FALSE): string
    {
        return static::getModulePath($type, $short) . 'unofficial/';
    }

    public static function moduleFileName(string $type, string $module): string
    {
        return $type . '_' . $module . '.zip';
    }

    public function getModuleFileName(): string
    {
        return static::moduleFileName($this->type, $this->module);
    }

    /**
     * Path of this volume's module file: the official directory for an official volume
     *
     * @param bool $short Relative to the project root, for messages
     * @return string
     */
    public function getModuleFilePath(bool $short = FALSE): string
    {
        $dir = $this->official ? static::getModulePath($this->type, $short) : static::getUnofficialModulePath($this->type, $short);

        return $dir . $this->getModuleFileName();
    }

    public function hasModuleFile(): bool
    {
        return is_file($this->getModuleFilePath());
    }

    public static function moduleFileIsOfficial(string $type, string $module): bool
    {
        return is_file(static::getModulePath($type) . static::moduleFileName($type, $module));
    }

    public function openModuleFile(): ?ZipArchive
    {
        if(!$this->hasModuleFile()) {
            return NULL;
        }

        $Zip = new ZipArchive();

        return ($Zip->open($this->getModuleFilePath()) === TRUE) ? $Zip : NULL;
    }

    /**
     * The module file's info.json, or NULL if there is no readable one
     *
     * @return array|null
     */
    public function readModuleInfo(): ?array
    {
        $Zip = $this->openModuleFile();

        if(!$Zip) {
            return NULL;
        }

        $info = json_decode((string) $Zip->getFromName(ContentBase::ZIP_META_FILE), TRUE);
        $Zip->close();

        return is_array($info) ? $info : NULL;
    }

    /**
     * info.json for this volume
     *
     * @param array|null $format delimiter / fields / escaped to keep, from an existing file
     * @return array
     */
    protected function buildModuleInfo(?array $format = NULL): array
    {
        $fields = static::getInfoFields($this->type);
        $attr   = $this->getAttributes();

        // A model saved without them has not loaded the columns' database defaults (copyright = 0 ...)
        $missing = array_values(array_diff($fields, array_keys($attr)));

        if($missing && $this->exists) {
            $attr += (array) (static::query()->withoutGlobalScopes()->whereKey($this->getKey())->first($missing)?->getAttributes() ?? []);
        }

        $info = [];

        foreach($fields as $field) {
            $info[$field] = $attr[$field] ?? NULL;
        }

        $content_class = static::getContentClassName($this->type);

        $info['delimiter'] = $format['delimiter'] ?? ContentBase::DELIMITER;
        $info['fields']    = $format['fields'] ?? ($content_class ? $content_class::EXPORT_FIELDS : []);
        $info['escaped']   = $format['escaped'] ?? TRUE;

        return $info;
    }

    protected static function encodeModuleInfo(array $info): string
    {
        return json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Creates the directory, if missing, and reports whether it can be written to
     */
    protected static function prepareModuleDirectory(string $dir): bool
    {
        if(!is_dir($dir)) {
            @mkdir($dir, 0775, TRUE);
        }

        return is_dir($dir) && is_writable($dir);
    }

    protected static function currentUser(): string
    {
        if(function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            return posix_getpwuid(posix_geteuid())['name'] ?? (string) posix_geteuid();
        }

        return get_current_user();
    }

    /**
     * Writes this volume's module file: info.json and contents.txt, zipped.
     *
     * The ZIP is built under a temporary name and renamed into place, and the record is only
     * stamped (module_version, needs_update) once that has worked, so a failed export changes
     * nothing.
     *
     * @param bool $overwrite Replace an existing module file
     * @return bool
     */
    public function export(bool $overwrite = FALSE): bool
    {
        $path  = $this->getModuleFilePath();
        $short = $this->getModuleFilePath(TRUE);

        if(!$this->installed) {
            return $this->addError('Cannot export, volume is not installed', 4);
        }

        if(is_file($path) && !$overwrite) {
            return $this->addError('Cannot export, file already exists: ' . $short, 4);
        }

        if(!static::prepareModuleDirectory(dirname($path)) || (is_file($path) && !is_writable($path))) {
            return $this->addError('Cannot write file: ' . $short . ' as user ' . static::currentUser(), 4);
        }

        $version  = config('app.version');
        $contents = $path . '.contents.tmp';
        $zip      = $path . '.tmp';

        try {
            $Content = $this->content();
            $info    = $this->buildModuleInfo();
            $info['module_version'] = $version;

            $handle = fopen($contents, 'w');

            if(!$handle) {
                throw new \RuntimeException('Cannot write temporary file for ' . $short);
            }

            $header = [
                'Bible SuperSearch Volume Module: ' . $this->name,
                'Type: ' . $this->type . ', Module: ' . $this->module,
                'For use with Bible SuperSearch >= ' . $version,
                'Separator: ' . ContentBase::DELIMITER . '  (escaped as \\' . ContentBase::DELIMITER . ')',
                'Escapes: \\\\ \\n \\r',
                'Columns: ' . implode(ContentBase::DELIMITER, $info['fields']),
            ];

            foreach($header as $line) {
                fwrite($handle, '# ' . str_replace(["\r", "\n"], ' ', $line) . "\n");
            }

            foreach($Content->exportRows() as $row) {
                if(fwrite($handle, $row . "\n") === FALSE) {
                    throw new \RuntimeException('Cannot write temporary file for ' . $short);
                }
            }

            fclose($handle);

            $Zip = new ZipArchive();

            if($Zip->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new \RuntimeException('Could not create ZIP file ' . $short);
            }

            $Zip->addFile($contents, ContentBase::ZIP_CONTENT_FILE);
            $Zip->addFromString(ContentBase::ZIP_META_FILE, static::encodeModuleInfo($info));

            if(!$Zip->close() || !rename($zip, $path)) {
                throw new \RuntimeException('Could not write ZIP file ' . $short);
            }
        }
        catch(\Throwable $e) {
            report($e);
            return $this->addError('Export failed: ' . $e->getMessage(), 4);
        }
        finally {
            is_file($contents) && @unlink($contents);
            is_file($zip) && @unlink($zip);
            clearstatcache(TRUE, $path);
        }

        $this->module_version = $version;
        $this->needs_update   = 0;
        $this->save();

        return TRUE;
    }

    /**
     * Export Meta: rewrites the module file's info.json from this volume's settings, leaving
     * contents.txt alone.  The file's own delimiter / fields / escaped are kept, as they describe
     * the contents.txt already in it.
     *
     * Nothing to change is not an error.
     *
     * @param bool $create_if_needed Export the whole module if there is no file yet
     * @return bool
     */
    public function updateMetaInfo(bool $create_if_needed = FALSE): bool
    {
        $short = $this->getModuleFilePath(TRUE);

        if(!$this->hasModuleFile()) {
            return $create_if_needed ? $this->export() : $this->addError('Cannot update info, file does not exist: ' . $short, 4);
        }

        if(!is_writable($this->getModuleFilePath())) {
            return $this->addError('Cannot write file: ' . $short . ' as user ' . static::currentUser(), 4);
        }

        $Zip = $this->openModuleFile();

        if(!$Zip) {
            return $this->addError('Cannot open file: ' . $short, 4);
        }

        $old_json = (string) $Zip->getFromName(ContentBase::ZIP_META_FILE);
        $old      = json_decode($old_json, TRUE);
        $new_json = static::encodeModuleInfo($this->buildModuleInfo(is_array($old) ? $old : NULL));

        if($old_json !== $new_json) {
            $Zip->addFromString(ContentBase::ZIP_META_FILE, $new_json);
        }

        if(!$Zip->close()) {
            return $this->addError('Could not write ZIP file ' . $short, 4);
        }

        clearstatcache(TRUE, $this->getModuleFilePath());

        // The file is now newer than the install; keep needsUpdate() from mistaking that for an update
        $this->installed_at = date('Y-m-d H:i:s');
        $this->needs_update = 0;
        $this->save();

        return TRUE;
    }

    /**
     * Revert: reloads this volume's settings from its module file.  Only the type's settings
     * fields are taken - never type, module or official.
     *
     * @return bool
     */
    public function revertMetaInfo(): bool
    {
        $short = $this->getModuleFilePath(TRUE);

        if(!$this->hasModuleFile()) {
            return $this->addError('Cannot revert info, file does not exist: ' . $short, 4);
        }

        $info = $this->readModuleInfo();

        if($info === NULL) {
            return $this->addError('Cannot read file: ' . $short, 4);
        }

        try {
            $this->fill(Arr::only($info, static::getSettingsFields($this->type)));
            $this->save();
        }
        catch(QueryException $e) {
            report($e);
            return $this->addError('Cannot revert info: the settings in ' . $short . ' clash with another volume', 4);
        }

        return TRUE;
    }

    /**
     * Whether the module file holds a newer module_version than the one installed.  Same logic
     * as Bible::needsUpdate(): the file's mtime saves opening the ZIP when it predates the install,
     * and an unreadable info.json keeps the stored flag.
     *
     * @return bool
     */
    public function needsUpdate(): bool
    {
        if(!$this->installed || !$this->hasModuleFile()) {
            return FALSE;
        }

        $install_ts = $this->installed_at ? strtotime($this->installed_at) : FALSE;
        $file_ts    = filemtime($this->getModuleFilePath());

        if($install_ts && $file_ts && $file_ts < $install_ts) {
            return (bool) $this->needs_update;
        }

        $info = $this->readModuleInfo();

        if($info === NULL) {
            return (bool) $this->needs_update;
        }

        $file_version = $info['module_version'] ?? '0';

        if($file_version == '0' && $this->needs_update == 1) {
            return TRUE;
        }

        $needs = ($file_version && version_compare($this->module_version ?? '0', $file_version) < 0);

        if((int) $this->needs_update !== (int) $needs) {
            $this->needs_update = $needs ? 1 : 0;
            $this->save();
        }

        return $needs;
    }

    public static function updateNeedsUpdate(): void
    {
        foreach(static::where('installed', 1)->get() as $Volume) {
            $Volume->needsUpdate();
        }
    }

    /**
     * Update: reinstalls the volume from its newer module file and takes the file's settings.
     * enabled and is_default are kept.
     *
     * @return bool
     */
    /**
     * Whether the module file looks installable: it opens, its info.json is readable, and it
     * has a non-empty contents.txt.  Adds an error and answers FALSE when not.
     *
     * Not a guarantee - a row could still fail to insert - but it catches the files that would.
     *
     * @return bool
     */
    public function moduleFileIsInstallable(): bool
    {
        $short = $this->getModuleFilePath(TRUE);
        $Zip   = $this->openModuleFile();

        if(!$Zip) {
            return $this->addError('Cannot open module file: ' . $short, 4);
        }

        $info     = json_decode((string) $Zip->getFromName(ContentBase::ZIP_META_FILE), TRUE);
        $contents = $Zip->statName(ContentBase::ZIP_CONTENT_FILE);
        $Zip->close();

        if(!is_array($info)) {
            return $this->addError('Module file has no readable ' . ContentBase::ZIP_META_FILE . ': ' . $short, 4);
        }

        if(!$contents || empty($contents['size'])) {
            return $this->addError('Module file has no ' . ContentBase::ZIP_CONTENT_FILE . ': ' . $short, 4);
        }

        return TRUE;
    }

    public function updateModule(): bool
    {
        if(!$this->needsUpdate()) {
            return $this->addError('No update needed.', 4, 422);
        }

        // Checked before uninstalling: a reinstall that then failed would leave the volume
        // uninstalled - and, for the default dictionary, the API with no dictionary at all
        if(!$this->moduleFileIsInstallable()) {
            return FALSE;
        }

        $info    = $this->readModuleInfo();
        $enabled = (bool) $this->enabled;

        if($this->installed) {
            $this->uninstall();

            if(!$this->install(FALSE, $enabled)) {
                return FALSE;
            }
        }

        if(!$this->revertMetaInfo()) {
            return FALSE;
        }

        $this->module_version    = $info['module_version'] ?? $this->module_version;
        $this->module_updated_at = date('Y-m-d H:i:s');
        $this->needs_update      = 0;
        $this->save();

        return TRUE;
    }

    /**
     * Module files of a type on disk: module name => official
     *
     * @param string $type
     * @return array<string, bool>
     */
    public static function getListOfModuleFiles(string $type): array
    {
        $files = [];

        // Unofficial first, so an official file of the same module wins
        foreach([FALSE => static::getUnofficialModulePath($type), TRUE => static::getModulePath($type)] as $official => $dir) {
            foreach(glob($dir . '*') ?: [] as $file) {
                $name = basename($file);

                if(is_file($file) && preg_match('/^' . preg_quote($type, '/') . '_(.+)\.zip$/i', $name, $matches)) {
                    $files[$matches[1]] = (bool) $official;
                }
            }
        }

        return $files;
    }

    /**
     * Creates an uninstalled volume record from a module file, if there is no record for it yet.
     *
     * type and module come from the file name and official from its directory; only the type's
     * settings fields (and module_version) are taken from info.json.  A file that cannot be read,
     * lacks a name / shortname / language, or clashes with an existing volume is skipped and
     * logged.
     *
     * @param string $type
     * @param string $module
     * @return static|null The new volume, or NULL if none was created
     */
    public static function createFromModuleFile(string $type, string $module): ?Volume
    {
        if(!isset(static::TYPES[$type]) || !static::validateModule($module) || static::findByTypeAndModule($type, $module)) {
            return NULL;
        }

        $class  = static::TYPES[$type]['volume_class'];
        $Volume = new $class();
        $Volume->type     = $type;
        $Volume->module   = $module;
        $Volume->official = static::moduleFileIsOfficial($type, $module) ? 1 : 0;

        $info = $Volume->readModuleInfo();

        if($info === NULL) {
            Log::warning('Volume module file skipped, info.json unreadable: ' . $Volume->getModuleFilePath(TRUE));
            return NULL;
        }

        // NULLs are left out, so a column's default applies rather than a NOT NULL failing
        $Volume->fill(array_filter(Arr::only($info, static::getSettingsFields($type)), fn($value) => $value !== NULL));
        $Volume->module_version = $info['module_version'] ?? config('app.version');

        foreach(['name', 'shortname', 'language'] as $required) {
            if(!is_string($Volume->$required) || $Volume->$required === '') {
                Log::warning('Volume module file skipped, no ' . $required . ': ' . $Volume->getModuleFilePath(TRUE));
                return NULL;
            }
        }

        try {
            $Volume->save();
        }
        catch(QueryException $e) {
            Log::warning('Volume module file skipped, could not be saved (it may clash with an existing volume): '
                . $Volume->getModuleFilePath(TRUE) . ': ' . $e->getMessage());
            return NULL;
        }

        return $Volume;
    }

    /**
     * Creates volume records for module files that have none.  Never changes an existing record.
     *
     * @return int Volumes created
     */
    public static function populateVolumesTable(): int
    {
        $created = 0;

        foreach(array_keys(static::TYPES) as $type) {
            foreach(array_keys(static::getListOfModuleFiles($type)) as $module) {
                $created += static::createFromModuleFile($type, (string) $module) ? 1 : 0;
            }
        }

        return $created;
    }

    public function language()
    {
        return $this->hasOne(Language::class, 'code', 'language');
    }

    public function copyrightInfo()
    {
        return $this->hasOne(Copyright::class, 'id', 'copyright_id');
    }

    /**
     * Installs the volume
     *
     * Phase 1: flags only.  Phase 2 adds creating the content table.
     *
     * @param bool $structure_only
     * @param bool $enable
     * @return bool
     */
    public function install(bool $structure_only = FALSE, bool $enable = FALSE): bool
    {
        if($this->installed) {
            return $this->addError('Already installed', 1);
        }

        // :todo - phase 2: create the content table, if it doesn't exist
        $success = $this->content()->install($structure_only);

        if(!$success) {
            $this->addError('Could not install content table', 4);
            return false;
        }

        $this->installed = 1;
        $this->installed_at = date('Y-m-d H:i:s');
        $this->module_updated_at = NULL;
        $this->needs_update = 0;

        if($enable) {
            $this->enabled = 1;
        }

        $this->save();
        return TRUE;
    }

    /**
     * Uninstalls the volume
     *
     * Phase 1: flags only.  Phase 2 adds dropping the content table.
     *
     * @return bool
     */
    public function uninstall(): bool
    {
        if(!$this->installed) {
            return $this->addError('Already uninstalled', 1);
        }

        $this->installed = 0;
        $this->enabled = 0;
        $this->installed_at = NULL;
        $this->module_updated_at = NULL;
        $this->save();
        // :todo - phase 2: drop the content table
        $this->content()->uninstall();
        return TRUE;
    }

    public function enable(): void
    {
        $this->enabled = 1;
        $this->save();
    }

    public function disable(): void
    {
        $this->enabled = 0;
        $this->save();
    }

    /**
     * The default volume of a type, if it is usable (installed and enabled)
     *
     * @param string $type
     * @return static|null
     */
    public static function getDefault(string $type): ?static
    {
        return static::where('type', $type)
            ->where('is_default', 1)
            ->where('installed', 1)
            ->where('enabled', 1)
            ->first();
    }

    public function isDefault(): bool
    {
        return (bool) $this->is_default;
    }

    /**
     * Makes this the default volume of its type, clearing any other default of that type.
     *
     * is_default is not fillable; this is the only way to set it.
     *
     * @return bool
     */
    public function makeDefault(): bool
    {
        if(!$this->installed || !$this->enabled) {
            return $this->addError('Only an installed, enabled volume can be the default', 4, 422);
        }

        DB::transaction(function () {
            static::where('type', $this->type)
                ->where('id', '!=', $this->id)
                ->where('is_default', 1)
                ->update(['is_default' => 0]);

            $this->is_default = 1;
            $this->save();
        });

        return TRUE;
    }

    /**
     * Enabled mutator - a volume cannot be enabled unless installed
     * @param mixed $value
     */
    public function setEnabledAttribute($value): void
    {
        $this->attributes['enabled'] = ($this->installed) ? $value : 0;
    }

    /**
     * Module mutator
     * @param mixed $value
     */
    public function setModuleAttribute($value): void
    {
        $this->attributes['module'] = is_string($value) ? strtolower($value) : $value;
    }

    /**
     * Description accessor / mutator, sanitized as for Bible::description()
     */
    protected function description(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => Helpers::sanitizeEditorHtml($value),
            set: fn (?string $value) => Helpers::sanitizeEditorHtml($value),
        );
    }

    /**
     * Copyright statement accessor / mutator, sanitized as for Bible::copyrightStatement()
     */
    protected function copyrightStatement(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => Helpers::sanitizeEditorHtml($value) ?? '',
            set: fn (?string $value) => Helpers::sanitizeEditorHtml($value) ?? '',
        );
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'copyright_id' => 'copyright',
        ];
    }
}
