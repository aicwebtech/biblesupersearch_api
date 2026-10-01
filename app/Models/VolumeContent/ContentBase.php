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

    protected static $table_prefix = null;

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
        $prefix = static::$table_prefix ?? $type . '_';
        return $prefix . $module;
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

    protected function generateTableName(): void
    {
        if ($this->type === null || $this->module === null) {
            \Log::warning('Cannot generate table name: type or module is null', [
                'type' => $this->type,
                'module' => $this->module,
            ]);
            
            return;
        }

        $this->table = static::getContentTableName($this->type, $this->module);
    } 

    public function install($structure_only = FALSE): bool
    {
        return true; // :todo - phase 5: implement content installation and exportation  

        $in_console = (strpos(php_sapi_name(), 'cli') !== FALSE);

        if (Schema::hasTable($this->table)) {
            return TRUE;
        }

        $tbl = $this->table;

        Schema::create($this->table, function (Blueprint $table) {
            $this->createSchema($table);
        });

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
     * Create the schema for the content table. 
     * This method must be implemented by subclasses to define the specific
     * schema for their content type.
     *
     * @param Blueprint $table The Blueprint instance used to define the table schema.
     */
    abstract protected function createSchema(Blueprint $table): void;
}