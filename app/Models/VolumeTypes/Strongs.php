<?php

namespace App\Models\VolumeTypes;

class Strongs extends VolumeTypeBase
{
    protected static $type = 'strongs';

    public static function getContentTableName(): string
    {
        return 'strongs';
    }
}