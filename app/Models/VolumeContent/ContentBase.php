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

    protected $zip_meta_file = 'info.json';
    protected $zip_content_file = 'content.txt';
    protected $zip_content_fields = []; // ie for a Bible: ["book","chapter","verse","text"];

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

    public function install($structure_only = FALSE): bool
    {
        $in_console = (strpos(php_sapi_name(), 'cli') !== FALSE);

        if (Schema::hasTable($this->table)) {
            return TRUE;
        }

        $tbl = $this->table;

        // MySQL and SQLite both create the table and then its indexes in separate statements, so
        // a failure part way leaves a table behind.  A later install would see that table and
        // report success, so drop it here.
        try {
            Schema::create($this->table, function (Blueprint $table) {
                $this->createSchema($table);
            });
        }
        catch(\Throwable $e) {
            Schema::dropIfExists($this->table);
            report($e);
            return FALSE;
        }

        return true; // :todo - phase 5: implement content installation and exportation  


        // Note: creating these indexes after the bulk insert instead was measured and rejected.
        // It is faster on SQLite (~7.5s vs ~11s for a 31k-verse Bible) but markedly slower on
        // MySQL (~42s vs ~30s), and MySQL is the production target.
        if($structure_only) {
            return TRUE;
        }

        // :todo - phase 5: bulk insert the content data from the module's zip file
        $Zip = $this->Volume->openModuleFile();

        if(!$Zip) {
            return FALSE;
        }

        $info = $Zip->getFromName($this->zip_meta_file);
        $rows = $Zip->getFromName($this->zip_content_file);
        $Zip->close();

        $info   = json_decode($info, TRUE);
        $del    = $info['delimiter'] ?? '|';
        $fields = $info['fields'] ?? $this->zip_content_fields;
        $rows   = preg_split("/\\r\\n|\\r|\\n/", $rows);
        $table  = $this->getTable();
        $insertable = [];
        $ins_count = 0;

        // Each verse binds one placeholder per mapped field plus chapter_verse, so the batch
        // has to fit the connection's bound-variable ceiling - 65535 on MySQL, but only 999 on
        // SQLite builds older than 3.32.
        $batch_size = \App\Helpers::getInsertChunkSize(count($fields) + 1, $this->getConnectionName());

        foreach($rows as $row) {
            if(empty($row) || $row[0] == '#') {
                continue;
            }

            $row = explode($del, $row);
            $map = [];

            foreach($fields as $index => $field) {
                $map[$field] = $row[$index];
            }

            $insertable[] = $this->processInsertRow($map);
            $ins_count ++;

            if($ins_count >= $batch_size) {
                DB::table($table)->insert($insertable);
                $insertable = [];
                $ins_count = 0;
            }
        }

        DB::table($table)->insert($insertable); // Finish inserting data
        return TRUE;
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