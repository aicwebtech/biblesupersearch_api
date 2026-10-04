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
    public static function getContentTableName(string $type, string $module)
    {
        $prefix = static::TYPE_TABLE_PREFIX ?? $type . '_';
        return static::CONTENT_TABLE_PREFIX . $prefix . $module;
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
     * Any failure drops the table again: MySQL and SQLite both create the table and then its
     * indexes in separate statements, so a failure part way would leave a table behind that a
     * later install would take as already installed.
     *
     * @param bool $structure_only
     * @return bool
     */
    public function install($structure_only = FALSE): bool
    {
        if (Schema::hasTable($this->table)) {
            return TRUE;
        }

        try {
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

        $info = json_decode((string) $Zip->getFromName(static::ZIP_META_FILE), TRUE);
        $data = $Zip->getFromName(static::ZIP_CONTENT_FILE);
        $Zip->close();

        if($data === FALSE) {
            throw new \RuntimeException('Module file has no ' . static::ZIP_CONTENT_FILE . ': ' . $this->Volume->getModuleFilePath(TRUE));
        }

        $fields  = (is_array($info) && !empty($info['fields']) && is_array($info['fields'])) ? array_values($info['fields']) : static::EXPORT_FIELDS;
        $columns = array_values(array_intersect($fields, static::EXPORT_FIELDS));
        $batch   = \App\Helpers::getInsertChunkSize(max(count($columns), 1), $this->getConnectionName());
        $table   = $this->getTable();
        $rows    = [];
        $count   = 0;

        foreach(preg_split("/\r\n|\r|\n/", $data) as $line) {
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
     * The content as encoded contents.txt rows, one per content row, in id order.
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
            }
            elseif($char === static::DELIMITER) {
                $fields[] = $current;
                $current  = '';
            }
            else {
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