<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Validation\Rule;
use App\Traits\Error;
use App\Helpers;
use App\Models\VolumeContent\ContentInterface;

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
                function($attribute, $value, $fail) {
                    if(!static::validateModule($value)) {
                        $fail('Module name is invalid: ' . static::$module_invalid_reason);
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
            $attributes = $this->getAttributes();
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

    public static function getContentClassName(string $type): ?string
    {
        return static::TYPES[$type]['content_class'] ?? NULL;
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
        // $success = $this->content()->install($structure_only);

        // if(!$success) {
        //     $this->addError('Could not install Bible table', 4);
        //     return false;
        // }

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
        // $this->content()->uninstall();
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
