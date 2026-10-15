<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

/**
 * Saved import settings, such as a spreadsheet column mapping, for reuse.
 *
 * The settings exclude what is chosen per import: the volume type (held in type), the importer
 * and the volume's own attributes.
 */
class ImportPreset extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Type for presets of the Bible importers; any other type is a volume type key
     */
    public const TYPE_BIBLE = 'bible';

    /**
     * Keys never stored in settings: they are chosen per import, not part of a preset
     */
    public const EXCLUDED_SETTINGS = [
        'importer', 'content_type', 'type', 'file', 'volume_id', 'bible_id', '_token',
        '_importer', '_content_type', '_file', '_settings',
    ];

    protected $fillable = ['type', 'name', 'settings'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * Types a preset can belong to: 'bible', or a registered volume type
     *
     * @return array<int, string>
     */
    public static function getValidTypes(): array
    {
        return array_merge([static::TYPE_BIBLE], array_keys(Volume::getTypes()));
    }

    /**
     * Validation rules; name is unique per type among presets that are not deleted
     *
     * @param string|null $type
     * @param int|null $preset_id
     * @return array<string, mixed>
     */
    public static function getRules(?string $type, ?int $preset_id = NULL): array
    {
        return [
            'type'     => ['required', Rule::in(static::getValidTypes())],
            'name'     => [
                'required', 'string', 'max:255',
                Rule::unique('import_presets')->where('type', (string) $type)->whereNull('deleted_at')->ignore($preset_id),
            ],
            'settings' => ['required', 'array'],
        ];
    }

    /**
     * Settings with the per-import keys removed
     *
     * @param array $settings
     * @return array
     */
    public static function cleanSettings(array $settings): array
    {
        return array_diff_key($settings, array_flip(static::EXCLUDED_SETTINGS));
    }
}
