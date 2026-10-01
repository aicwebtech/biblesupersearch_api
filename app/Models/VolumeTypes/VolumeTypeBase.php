<?php

namespace App\Models\VolumeTypes;

use App\Volume;

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
     * Find a volume by its module name and type.
     *
     * @param string $module The module name to search for.
     * @param bool $fail Whether to throw an exception if not found (default: false).
     * @return static|null The found volume instance or null if not found and $fail is false.
     * @throws \Exception If the subclass does not define a type or if $fail is true and no volume is found.
     */
    public static function findByModule(string $module, bool $fail = false): ?static
    {
        if(static::$type === null) {
            throw new \Exception('Volume type must be defined in the subclass');
        }

        $query = static::where('type', static::$type)->where('module', $module);

        if ($fail) {
            return $query->firstOrFail();
        }

        return $query->first();
    }

    public static function getType(): string
    {
        return static::$type;
    }

    public static function getLabel(): string
    {
        return static::getTypes()[static::$type]['label'] ?? '';
    }

    public static function getContentTableName(): string
    {
        throw new \Exception('getContentTableName() must be implemented in the subclass');
    }
}