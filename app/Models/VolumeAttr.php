<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A volume setting attached to another record, named by (type, module): for example a Bible's
 * default Strong's dictionary is (TYPE_BIBLE, <Bible module>, Strongs::BIBLE_ATTR) => <dictionary module>.
 *
 * Like language_attr, the table has no timestamps.
 */
class VolumeAttr extends Model
{
    /**
     * Record type for attributes of a Bible
     */
    public const TYPE_BIBLE = 'bible';

    public $timestamps = false;
    protected $table = 'volumes_attr';

    protected $fillable = [
        'module', 'type', 'attribute', 'value',
    ];

    /**
     * The value of one attribute of a record
     */
    public static function getAttr(string $type, string $module, string $attribute): ?string
    {
        $value = static::where('type', $type)
            ->where('module', $module)
            ->where('attribute', $attribute)
            ->value('value');

        return ($value === NULL) ? NULL : (string) $value;
    }

    /**
     * Sets one attribute of a record, replacing any value it had
     */
    public static function setAttr(string $type, string $module, string $attribute, string $value): void
    {
        static::updateOrCreate(
            ['type' => $type, 'module' => $module, 'attribute' => $attribute],
            ['value' => $value]
        );
    }

    /**
     * Removes one attribute of a record
     */
    public static function unsetAttr(string $type, string $module, string $attribute): void
    {
        static::where('type', $type)
            ->where('module', $module)
            ->where('attribute', $attribute)
            ->delete();
    }

    /**
     * Removes every attribute of a record, as when it is deleted
     */
    public static function unsetAll(string $type, string $module): void
    {
        static::where('type', $type)->where('module', $module)->delete();
    }

    /**
     * Moves a record's attributes to its new module name
     */
    public static function renameModule(string $type, string $old, string $new): void
    {
        if($old === $new) {
            return;
        }

        // Rows already under the new name belong to nothing (a module names one record of its
        // type) and would break the unique key: left by a delete that skipped the model's
        // events, such as a query builder delete
        static::where('type', $type)->where('module', $new)->delete();
        static::where('type', $type)->where('module', $old)->update(['module' => $new]);
    }

    /**
     * Removes every attribute whose value is the given module, as when that volume is deleted
     */
    public static function unsetByValue(string $attribute, string $value): void
    {
        static::where('attribute', $attribute)->where('value', $value)->delete();
    }
}
