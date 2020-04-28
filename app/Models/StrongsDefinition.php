<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
