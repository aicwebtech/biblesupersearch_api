<?php

namespace App\Models\VolumeContent;

use Illuminate\Database\Schema\Blueprint;

/**
 * Content table name comes from ContentBase::getContentTableName() (content_<type>_<module>).
 * :todo - phase 2: settle table naming; a no-argument override here is a fatal signature clash.
 */
class StrongsContent extends ContentBase
{
    protected static $table_prefix = 'stro_';

    /**
     * :todo - phase 3: Strong's schema (number, root_word, pronunciation, transliteration,
     * definition_html, definition_md, short_definition, is_special, timestamps)
     */
    protected function createSchema(Blueprint $table): void
    {
        throw new \LogicException('Strong\'s content schema is not implemented yet (BSS-152 phase 3)');
    }
}
