<?php

namespace App\Models\VolumeContent;

class StrongsContent extends ContentBase
{
    public static function getContentTableName(): string
    {
        return 'strongs';
    }
}