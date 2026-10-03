<?php

namespace App\Models\VolumeTypes;

use App\Models\Volume;
use Illuminate\Database\Eloquent\Builder;

abstract class VolumeTypeBase extends Volume
{
    protected $table = 'volumes';

    /**
     * The type of volume this class represents. Must be set in the subclass.
     * For these subclasses, the type is a constant, so it is not fillable and cannot be changed.
     *
     * @var string|null
     */
    protected static $type = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (static::$type === null) {
            throw new \Exception('Volume type must be defined in the subclass');
        }

        $this->type = static::$type;
    }

    /**
     * Every query on a type subclass is limited to that type, so Strongs::all(),
     * Strongs::where(...) and the like never return volumes of another type.
     * Use Volume (or withoutGlobalScope('volume_type')) to query across types.
     */
    protected static function booted(): void
    {
        $type = static::$type;

        static::addGlobalScope('volume_type', function (Builder $query) use ($type) {
            $query->where($query->qualifyColumn('type'), $type);
        });
    }

    /**
     * Find a volume of this type by its module name.
     *
     * @param string $module The module name to search for.
     * @param bool $fail Whether to throw an exception if not found (default: false).
     * @return static|null The found volume instance or null if not found and $fail is false.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If $fail is true and no volume is found.
     */
    public static function findByModule(string $module, bool $fail = false): ?static
    {
        $query = static::where('module', $module);

        if ($fail) {
            return $query->firstOrFail();
        }

        return $query->first();
    }

    /**
     * Not getType(): that would override Volume::getType(string $type), which returns a type's settings.
     */
    public static function getTypeName(): string
    {
        return static::$type;
    }

    public static function getLabel(): string
    {
        return static::getTypes()[static::$type]['label'] ?? '';
    }
}
