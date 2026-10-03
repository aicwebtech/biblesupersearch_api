<?php

namespace App\Models\VolumeContent;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use App\Helpers;

/**
 * Content table: cn_stro_<module>, see ContentBase::getContentTableName().
 */
class StrongsContent extends ContentBase
{
    public const TYPE_TABLE_PREFIX = 'stro_';

    /**
     * The legacy, single-dictionary table this content type replaces
     */
    public const LEGACY_TABLE = 'strongs_definitions';

    /**
     * Legacy columns holding entity-encoded plain text, decoded to UTF-8 on import
     */
    protected const LEGACY_DECODE_COLUMNS = ['root_word', 'transliteration', 'pronunciation'];

    /**
     * Removes the '<b>Count:</b> n ...<br>' segment from a legacy tense/voice/mood entry.
     *
     * Same pattern Engine::_formatStrongs() applies to the legacy table on the way out; that copy
     * goes away when the API reads from volumes (BSS-152 phase 3).
     *
     * @param string|null $tvm
     * @return string|null NULL for an empty entry
     */
    public static function stripTvmCount(?string $tvm): ?string
    {
        if($tvm === NULL || $tvm === '') {
            return NULL;
        }

        return preg_replace('/<b>Count:<\/b> [0-9]+.*?<br>/', '', $tvm);
    }

    /**
     * Maps one legacy strongs_definitions row to a row of this content table.
     *
     * Legacy rows hold either an entry or a tvm (tense/voice/mood) entry, never both.  The
     * definition is the entry, falling back to the tvm entry; tvm rows are flagged is_special.
     * The plain text columns are entity-encoded in the legacy data and are decoded to UTF-8;
     * the definition is HTML and is copied as is.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function mapLegacyRow(array $row): array
    {
        $entry = $row['entry'] ?? NULL;
        $tvm   = $row['tvm'] ?? NULL;
        $from_tvm = ($entry === NULL || $entry === '') && $tvm !== NULL && $tvm !== '';

        $mapped = [
            'id'               => $row['id'] ?? NULL,
            'number'           => $row['number'],
            'definition'       => $from_tvm ? static::stripTvmCount($tvm) : (($entry === '') ? NULL : $entry),
            'short_definition' => NULL,
            'is_special'       => $from_tvm ? 1 : 0,
            'created_at'       => $row['created_at'] ?? NULL,
            'updated_at'       => $row['updated_at'] ?? NULL,
        ];

        foreach(static::LEGACY_DECODE_COLUMNS as $column) {
            $value = $row[$column] ?? NULL;
            $mapped[$column] = ($value === NULL) ? NULL : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $mapped;
    }

    /**
     * Copies the legacy strongs_definitions table into this content table.
     *
     * Reads with the query builder rather than StrongsDefinition, whose accessors would
     * sanitize the raw values on the way out.
     *
     * @return int Number of rows copied
     */
    public function importLegacy(): int
    {
        $table   = $this->getTable();
        $columns = array_keys(static::mapLegacyRow(['number' => '']));
        $batch   = Helpers::getInsertChunkSize(count($columns), $this->getConnectionName());
        $count   = 0;

        DB::table(static::LEGACY_TABLE)->orderBy('id')->chunk($batch, function($rows) use ($table, &$count) {
            $insertable = [];

            foreach($rows as $row) {
                $insertable[] = static::mapLegacyRow((array) $row);
            }

            DB::table($table)->insert($insertable);
            $count += count($insertable);
        });

        return $count;
    }

    protected function createSchema(Blueprint $table): void
    {
        $table->charset = 'utf8mb4';
        $table->collation = 'utf8mb4_unicode_ci';

        $table->increments('id');
        $table->string('number', 10);
        $table->string('root_word')->nullable();
        $table->string('pronunciation')->nullable();
        $table->string('transliteration')->nullable();
        $table->text('definition')->nullable();
        $table->string('short_definition')->nullable();
        $table->tinyInteger('is_special')->default(0)->unsigned();
        $table->timestamps();

        $table->unique('number', static::indexName($table, 'number'));
    }
}
