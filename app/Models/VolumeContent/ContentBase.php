<?php

namespace App\Models\VolumeContent;

use App\Models\Volume;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

abstract class ContentBase extends Model implements ContentInterface
{
    protected $Volume = null;
    protected $table = null;

    public const CONTENT_TABLE_PREFIX = 'cn_';

    /**
     * Per-type table prefix, after CONTENT_TABLE_PREFIX.  NULL falls back to '<type>_'.
     * Keep it short: the full table name must fit Volume::MAX_TABLE_NAME_LENGTH.
     */
    public const TYPE_TABLE_PREFIX = null;

    protected $type = null;
    protected $module = null;

    public const ZIP_META_FILE = 'info.json';
    public const ZIP_CONTENT_FILE = 'contents.txt';
    public const DELIMITER = '|';

    /**
     * The human-readable labels for each field.  Leave empty to generate from the field names.
     * @var array<string, string>
     */
    public const FIELD_LABELS = [];

    /**
     * The fields on each content table that are importable.
     * @var array<string>
     */
    public const IMPORT_FIELDS = [];

    /**
     * Extra fields that don't exist in the table but can be mapped in. 
     * @var array<string, string>
     */
    public const IMPORT_FIELDS_EXTRA = [];

    /**
     * Order the import fields are offered in, by key: any of IMPORT_FIELDS and
     * IMPORT_FIELDS_EXTRA.  Fields not listed follow, in their default order (IMPORT_FIELDS,
     * then IMPORT_FIELDS_EXTRA); keys that are not import fields are ignored.
     * @var array<int, string>
     */
    public const IMPORT_FIELD_ORDER = [];

    /**
     * Fields an imported row must have once mapped and formatted.  A field listed in
     * IMPORT_FIELD_ALTERNATIVES can be mapped through one of its alternatives instead.
     * @var array<string>
     */
    public const IMPORT_REQUIRED_FIELDS = [];

    /**
     * Other ways of supplying a required field: field => list of field sets, each of which
     * supplies it when every field in the set is mapped.  The type's import formatter builds
     * the field from the set.
     * @var array<string, array<int, array<int, string>>>
     */
    public const IMPORT_FIELD_ALTERNATIVES = [];

    /**
     * Fields that must be unique across an import, as the content table enforces
     * @var array<string>
     */
    public const IMPORT_UNIQUE_FIELDS = [];

    /**
     * Content table columns written to the module's contents.txt, in order.  Set per type.
     *
     * Only ever add to the end: module files record their own field list, but a reordering
     * would still read an older file into the wrong columns if that list were ever missing.
     */
    public const EXPORT_FIELDS = [];

    /**
     * Get the name of the content table associated with this content type.
     *
     * @param string $type The type of volume (e.g., 'strongs').
     * @param string $module The module name associated with the volume.
     * @return string The name of the content table.
     */
    public static function getContentTableName(string $type, string $module): string
    {
        $prefix = static::TYPE_TABLE_PREFIX ?? $type . '_';
        return static::CONTENT_TABLE_PREFIX . $prefix . $module;
    }

    /**
     * Import fields and their labels, ordered by IMPORT_FIELD_ORDER
     *
     * @param bool $include_extra Include IMPORT_FIELDS_EXTRA
     * @return array<string, string> field => label
     */
    public static function getImportFields(bool $include_extra = FALSE): array
    {
        $fields = [];

        foreach(static::IMPORT_FIELDS as $field) {
            $fields[$field] = static::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));
        }
    
        if($include_extra) {
            foreach(static::IMPORT_FIELDS_EXTRA as $field => $label) {
                $fields[$field] = $label;
            }
        }

        $ordered = array_intersect_key(array_flip(static::IMPORT_FIELD_ORDER), $fields);

        return array_replace($ordered, $fields);
    }

    /**
     * The mappable import fields, for the import UI
     *
     * @return array<int, array{value: string, title: string, required: bool}>
     */
    public static function getImportFieldList(): array
    {
        $list = [];

        foreach(static::getImportFields(TRUE) as $field => $label) {
            $list[] = [
                'value'    => $field,
                'title'    => $label,
                'required' => in_array($field, static::IMPORT_REQUIRED_FIELDS, TRUE),
            ];
        }

        return $list;
    }

    /**
     * Whether a mapping supplies a required field, directly or through one of its alternatives
     *
     * @param string $field Required field
     * @param array<int, string> $mapped Fields the mapping supplies
     * @return bool
     */
    public static function importFieldIsMapped(string $field, array $mapped): bool
    {
        if(in_array($field, $mapped, TRUE)) {
            return TRUE;
        }

        foreach(static::IMPORT_FIELD_ALTERNATIVES[$field] ?? [] as $set) {
            if(!array_diff($set, $mapped)) {
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Validates one mapped and formatted import row.  Pure PHP, no database.
     *
     * Checks the required fields; types add their own checks.
     *
     * @param array<string, mixed> $row
     * @return array<int, string> Error messages, empty if the row is valid
     */
    public static function validateImportRow(array $row): array
    {
        $errors = [];
        $labels = static::getImportFields(TRUE);

        foreach(static::IMPORT_REQUIRED_FIELDS as $field) {
            if(!isset($row[$field]) || $row[$field] === '') {
                $errors[] = ($labels[$field] ?? $field) . ' is required';
            }
        }

        return $errors;
    }

    /**
     * Shapes a validated import row for insertion: only the importable columns, every one
     * present, and an empty value is NULL.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function prepareImportRow(array $row): array
    {
        $prepared = [];

        foreach(static::IMPORT_FIELDS as $field) {
            $value = $row[$field] ?? NULL;
            $prepared[$field] = ($value === NULL || $value === '') ? NULL : $value;
        }

        return $prepared;
    }

    public function setVolume(Volume $volume): void
    {
        $this->Volume = $volume;
        $this->type = $volume->type;
        $this->module = $volume->module;
        $this->generateTableName();
    }

    public function setModule(string $module): void
    {
        $this->module = $module;
        $this->generateTableName();
    }

    /**
     * 
     * Generate the table name for the content based on the type and module.
     * If either the type or module is null, it will throw a LogicException unless $
     * silent is true, in which case it will return false.
     *  
     * @param bool $silent Whether to suppress exceptions and return false instead (default: false).
     * @return bool True if the table name was generated successfully, false if silent and type or module is null.
     * @throws \LogicException If type or module is null and silent is false.
     */
    protected function generateTableName(bool $silent = false): bool
    {
        if ($this->type === null || $this->module === null) {
            
            if($silent) {
                return false;
            }

            throw new \LogicException('Cannot generate table name: type or module is null');            
        }

        $this->table = static::getContentTableName($this->type, $this->module);
        return true;
    } 

    /**
     * Creates the content table and, unless $structure_only, fills it from the volume's module
     * file if it has one.  Without a module file the table is left empty (structure only).
     *
     * A table that already exists is dropped first.  Only an uninstalled volume is installed,
     * so whatever it holds is left over (from a failed drop, or a deleted volume with the same
     * module) and must not be taken as this install's content.
     *
     * Any failure drops the table again: MySQL and SQLite both create the table and then its
     * indexes in separate statements, so a failure part way would leave a table behind.
     *
     * @param bool $structure_only
     * @return bool
     */
    public function install(bool $structure_only = FALSE): bool
    {
        try {
            Schema::dropIfExists($this->table);

            Schema::create($this->table, function (Blueprint $table) {
                $this->createSchema($table);
            });

            if(!$structure_only && $this->Volume && $this->Volume->hasModuleFile()) {
                $this->importModuleFile();
            }
        }
        catch(\Throwable $e) {
            Schema::dropIfExists($this->table);
            report($e);
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Fills the content table from the volume's module file (contents.txt).
     *
     * Values are mapped by the file's own field list, falling back to EXPORT_FIELDS.  Columns
     * the table does not know are ignored, missing ones are NULL, and an empty field is NULL.
     *
     * @return int Rows inserted
     * @throws \RuntimeException If the module file cannot be read
     */
    protected function importModuleFile(): int
    {
        $Zip = $this->Volume->openModuleFile();

        if(!$Zip) {
            throw new \RuntimeException('Could not open module file: ' . $this->Volume->getModuleFilePath(TRUE));
        }

        $info   = json_decode((string) $Zip->getFromName(static::ZIP_META_FILE), TRUE);
        $stream = $Zip->getStream(static::ZIP_CONTENT_FILE);

        if($stream === FALSE) {
            $Zip->close();
            throw new \RuntimeException('Module file has no ' . static::ZIP_CONTENT_FILE . ': ' . $this->Volume->getModuleFilePath(TRUE));
        }

        try {
            return $this->importContentStream($stream, $info);
        }
        finally {
            fclose($stream);
            $Zip->close();
        }
    }

    /**
     * Inserts the rows of an open contents.txt stream.  Read a line at a time, so a large
     * module file is never held in memory at once.
     *
     * @param resource $stream mixed in the signature, as a resource has no type declaration
     * @param mixed $info Decoded info.json
     * @return int Rows inserted
     */
    protected function importContentStream(mixed $stream, mixed $info): int
    {
        if(!is_resource($stream)) {
            throw new \InvalidArgumentException('contents.txt stream is not open');
        }

        $fields  = (is_array($info) && !empty($info['fields']) && is_array($info['fields'])) ? array_values($info['fields']) : static::EXPORT_FIELDS;
        $columns = array_values(array_intersect($fields, static::EXPORT_FIELDS));
        $batch   = \App\Helpers::getInsertChunkSize(max(count($columns), 1), $this->getConnectionName());
        $table   = $this->getTable();
        $rows    = [];
        $count   = 0;

        foreach(static::streamLines($stream) as $line) {
            if($line === '' || $line[0] == '#') {
                continue;
            }

            $values = static::decodeRow($line);
            $row    = [];

            foreach($fields as $index => $field) {
                if(in_array($field, $columns, TRUE)) {
                    $value = $values[$index] ?? NULL;
                    $row[$field] = ($value === NULL || $value === '') ? NULL : $value;
                }
            }

            foreach($columns as $column) {
                $row[$column] = $row[$column] ?? NULL;
            }

            $rows[] = $this->processInsertRow($row);
            $count++;

            if(count($rows) >= $batch) {
                DB::table($table)->insert($rows);
                $rows = [];
            }
        }

        if($rows) {
            DB::table($table)->insert($rows);
        }

        return $count;
    }

    /**
     * The lines of a stream, without their line endings.  fgets() splits on \n only, so a bare
     * \r is split on here.
     *
     * @param resource $stream mixed in the signature, as a resource has no type declaration
     * @return \Generator<int, string>
     */
    protected static function streamLines(mixed $stream): \Generator
    {
        if(!is_resource($stream)) {
            throw new \InvalidArgumentException('Stream is not open');
        }

        while(($line = fgets($stream)) !== FALSE) {
            yield from explode("\r", rtrim($line, "\r\n"));
        }
    }

    /**
     * The content as encoded contents.txt rows, one per content row, in id order, for 
     * export to a module file.  The rows are encoded with encodeRow() and the fields are in EXPORT_FIELDS order.
     *
     * A generator over chunked reads, so a large table is never held in memory at once.
     *
     * @return \Generator<int, string>
     */
    public function exportRows(): \Generator
    {
        foreach($this->newQuery()->lazyById(1000) as $Row) {
            $attr = $Row->getAttributes();
            yield static::encodeRow(array_map(fn($field) => $attr[$field] ?? NULL, static::EXPORT_FIELDS));
        }
    }

    /**
     * Encodes one value for contents.txt: trimmed, then backslash-escaped so a row stays on one
     * line and the delimiter is never ambiguous.  NULL is the empty field; 0 stays '0'.
     *
     * @param mixed $value
     * @return string
     */
    public static function encodeField(mixed $value): string
    {
        if($value === NULL) {
            return '';
        }

        return str_replace(
            ['\\', "\n", "\r", static::DELIMITER],
            ['\\\\', '\\n', '\\r', '\\' . static::DELIMITER],
            trim((string) $value)
        );
    }

    /**
     * @param array $values
     * @return string One contents.txt line
     */
    public static function encodeRow(array $values): string
    {
        return implode(static::DELIMITER, array_map([static::class, 'encodeField'], $values));
    }

    /**
     * Decodes one contents.txt line: splits on unescaped delimiters and unescapes, in one pass.
     *
     * An unknown escape keeps its backslash, as does a trailing lone backslash.
     *
     * @param string $line
     * @return array<int, string>
     */
    public static function decodeRow(string $line): array
    {
        $fields  = [];
        $current = '';
        $length  = strlen($line);

        for($i = 0; $i < $length; $i++) {
            $char = $line[$i];

            if($char === '\\' && $i + 1 < $length) {
                $next = $line[++$i];

                $current .= match($next) {
                    'n'     => "\n",
                    'r'     => "\r",
                    '\\'    => '\\',
                    static::DELIMITER => static::DELIMITER,
                    default => '\\' . $next,
                };
            } elseif($char === static::DELIMITER) {
                $fields[] = $current;
                $current  = '';
            } else {
                $current .= $char;
            }
        }

        $fields[] = $current;
        return $fields;
    }

    /**
     * Process a row before inserting it into the database.
     * Subclasses can override this method to modify the row as needed.
     *
     * @param array $row The row data to be processed.
     * @return array The processed row data.
     */
    protected function processInsertRow(array $row): array
    {
        return $row;
    }

    public function uninstall(): bool
    {
        if (Schema::hasTable($this->table)) {
            Schema::drop($this->table);
        }

        return TRUE;
    }

    /**
     * Copies the content table's rows to a backup table, so content that is about to be
     * replaced can be put back if the replacement fails.  See restoreFromBackup().
     *
     * The copy has no indexes: SQLite needs index names unique across the whole database, so a
     * renamed table would keep the names the replacement table is about to create.
     *
     * @return string|null The backup table, or NULL when there is no content table to back up
     */
    public function backup(): ?string
    {
        if(!Schema::hasTable($this->table)) {
            return NULL;
        }

        $backup  = static::backupTableName($this->table);
        $Grammar = $this->getConnection()->getQueryGrammar();

        Schema::dropIfExists($backup);
        $this->getConnection()->statement('CREATE TABLE ' . $Grammar->wrapTable($backup) . ' AS SELECT * FROM ' . $Grammar->wrapTable($this->table));

        return $backup;
    }

    /**
     * Rebuilds the content table from a backup() table, then drops the backup.  The backup is
     * kept if anything fails.
     *
     * @param string $backup Table backup() returned
     * @return bool
     */
    public function restoreFromBackup(string $backup): bool
    {
        if(!Schema::hasTable($backup) || !$this->install(TRUE)) {
            return FALSE;
        }

        $Grammar = $this->getConnection()->getQueryGrammar();
        $columns = array_intersect(Schema::getColumnListing($this->table), Schema::getColumnListing($backup));
        $columns = implode(', ', array_map([$Grammar, 'wrap'], $columns));

        try {
            $this->getConnection()->statement(
                'INSERT INTO ' . $Grammar->wrapTable($this->table) . ' (' . $columns . ') SELECT ' . $columns . ' FROM ' . $Grammar->wrapTable($backup)
            );
        }
        catch(\Throwable $e) {
            report($e);
            return FALSE;
        }

        Schema::drop($backup);
        return TRUE;
    }

    /**
     * Backup table name for a content table: short enough for Volume::MAX_TABLE_NAME_LENGTH
     * whatever the module, and distinct per content table.
     *
     * @param string $table Content table
     * @return string
     */
    public static function backupTableName(string $table): string
    {
        return static::CONTENT_TABLE_PREFIX . 'bak_' . substr(md5($table), 0, 16);
    }

    /**
     * Name for an index on a content table.
     *
     * Laravel's default, <table>_<columns>_<type>, can exceed MySQL's 64 character limit for a
     * long module name.  A fixed name such as 'ux_number' would be short enough, and MySQL only
     * needs index names unique per table, but SQLite needs them unique across the whole
     * database, so a short hash of the table name keeps each one distinct.
     *
     * @param Blueprint $table
     * @param string $column Column(s) the index covers, as used in the name
     * @param string $type 'ux' for unique, 'ix' for a plain index
     * @return string
     */
    protected static function indexName(Blueprint $table, string $column, string $type = 'ux'): string
    {
        return $type . '_' . substr(md5($table->getTable()), 0, 12) . '_' . $column;
    }

    /**
     * Create the schema for the content table.
     * This method must be implemented by subclasses to define the specific
     * schema for their content type.
     *
     * @param Blueprint $table The Blueprint instance used to define the table schema.
     */
    abstract protected function createSchema(Blueprint $table): void;
}