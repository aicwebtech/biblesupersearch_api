<?php

namespace App\Models\VolumeContent;

use Illuminate\Database\Schema\Blueprint;

/**
 * Content table: cn_stro_<module>, see ContentBase::getContentTableName().
 */
class StrongsContent extends ContentBase
{
    public const TYPE_TABLE_PREFIX = 'stro_';

    /**
     * The fields on each content table that are importable.
     * @var array<string>
     */
    public const IMPORT_FIELDS = ['number', 'root_word', 'transliteration', 'pronunciation', 'definition', 'short_definition'];

    /**
     * Extra fields that don't exist in the table but can be mapped in. 
     * @var array<string, string>
     */
    public const IMPORT_FIELDS_EXTRA = [
        'number_int' => 'Strong\'s number - integer portion only (no H/G prefix)',
        'number_hg' => 'Strong\'s number - H/G prefix only',
        'tvm' => 'Tense/Voice/Mood (TVM) entry (mapped to definition if it\'s empty)',
    ];

    /**
     * The three ways of mapping the number are offered together
     */
    public const IMPORT_FIELD_ORDER = ['number', 'number_hg', 'number_int'];

    public const IMPORT_REQUIRED_FIELDS = ['number', 'definition'];

    public const FIELD_LABELS = [
        'number' => 'Strong\'s number - entire (ie H1, G25, H1234)',
    ];

    /**
     * number can come from its prefix and integer in separate columns; StrongsFormatter joins them
     */
    public const IMPORT_FIELD_ALTERNATIVES = [
        'number' => [['number_hg', 'number_int']],
    ];

    public const IMPORT_UNIQUE_FIELDS = ['number'];

    /**
     * A Strong's number as stored: H or G, then the number without leading zeros.
     *
     * No variant letter (H1234a, as extended Strong's schemes use): the API's lookup matches
     * H or G and digits only, so a variant could be imported but never returned.
     */
    public const NUMBER_PATTERN = '/^[HG][1-9][0-9]{0,4}$/';

    /**
     * Columns limited to 255 characters
     */
    protected const IMPORT_SHORT_FIELDS = ['root_word', 'transliteration', 'pronunciation', 'short_definition'];

    /**
     * contents.txt columns.  Only ever add to the end - see ContentBase::EXPORT_FIELDS.
     * No id or timestamps: ids are generated on install.
     */
    public const EXPORT_FIELDS = [
        'number', 'root_word', 'transliteration', 'pronunciation', 'definition', 'short_definition', 'is_special',
    ];

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
     * Used by the importers and by Engine::_formatStrongs() on the legacy table's way out.
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
     * Shapes one row of this content table as a legacy strongs_definitions row: the reverse of
     * mapLegacyRow(), for answering the legacy format from a dictionary volume.
     *
     * A special (tense/voice/mood) row's definition is the tvm entry, any other's the entry.
     * The plain text columns stay UTF-8 rather than being entity-encoded again: either is the
     * same text to a client, and a modern dictionary may hold characters no entity names.
     *
     * Nothing is sanitized here; the caller does that, as the legacy model's accessors would.
     *
     * @param array<string, mixed> $row
     * @return array{id: int|null, number: string, root_word: ?string, transliteration: ?string, pronunciation: ?string, tvm: ?string, entry: ?string}
     */
    public static function mapToLegacyRow(array $row): array
    {
        $special    = !empty($row['is_special']);
        $definition = $row['definition'] ?? NULL;

        return [
            'id'              => isset($row['id']) ? (int) $row['id'] : NULL,
            'number'          => (string) ($row['number'] ?? ''),
            'root_word'       => $row['root_word'] ?? NULL,
            'transliteration' => $row['transliteration'] ?? NULL,
            'pronunciation'   => $row['pronunciation'] ?? NULL,
            'tvm'             => $special ? $definition : NULL,
            'entry'           => $special ? NULL : $definition,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<int, string>
     */
    public static function validateImportRow(array $row): array
    {
        $errors = parent::validateImportRow($row);
        $labels = static::getImportFields();
        $number = $row['number'] ?? NULL;

        if($number !== NULL && $number !== '' && !preg_match(static::NUMBER_PATTERN, (string) $number)) {
            $errors[] = 'Strong\'s number is invalid: \'' . $number . '\'.  Expected H or G followed by a number, such as H1 or G25';
        }

        foreach(static::IMPORT_SHORT_FIELDS as $field) {
            if(isset($row[$field]) && mb_strlen((string) $row[$field]) > 255) {
                $errors[] = ($labels[$field] ?? $field) . ' may not be longer than 255 characters';
            }
        }

        return $errors;
    }

    /**
     * is_special comes from the formatted row: 1 when its definition was taken from a tense /
     * voice / mood entry (see StrongsFormatter::applyTvm()), otherwise 0.
     */
    public static function prepareImportRow(array $row): array
    {
        $is_special = (int) ($row['is_special'] ?? 0);
        $row = parent::prepareImportRow($row);
        $row['is_special'] = $is_special;
        return $row;
    }

    /**
     * is_special is NOT NULL: an empty field, or one an older module file lacks, is 0
     */
    protected function processInsertRow(array $row): array
    {
        $row['is_special'] = (int) ($row['is_special'] ?? 0);
        return $row;
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
