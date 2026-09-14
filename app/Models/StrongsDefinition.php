<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Helpers;

class StrongsDefinition extends Model
{
    protected $fillable = [
        'id', 'number', 'root_word', 'transliteration', 'pronunciation', 'tvm', 'entry', 
    ];

    public static function migrateFromCsv() {
        $map = [
            'id', 'number', 'root_word', 'transliteration', 'pronunciation', 'tvm', 'entry', 
        ];

        \App\Importers\Database::importCSV('strongs_definitions.csv', $map, '\\' . get_called_class(), 'number', NULL, 1000);
    }

    /**
     * Root word accessor / mutator.
     *
     * A NULL stays NULL - most of these columns are empty on most rows, and '/api/strongs'
     * has always reported them as null.
     */
    protected function rootWord(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value === NULL) ? NULL : Helpers::sanitizeHtml($value),
            set: fn (?string $value) => ($value === NULL) ? NULL : Helpers::sanitizeHtml($value),
        );
    }

    /**
     * Entry accessor / mutator. A NULL stays NULL - see rootWord().
     *
     */
    protected function entry(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ($value === NULL) ? NULL : Helpers::sanitizeHtml($value),
            set: fn (?string $value) => ($value === NULL) ? NULL : Helpers::sanitizeHtml($value),
        );
    }
}
