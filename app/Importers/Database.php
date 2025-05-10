<?php
namespace App\Importers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Collection;
use \DB;

/*
 * Generic importer for importing database dumps
 */

class Database 
{
    public static $use_queue = FALSE;
    protected static $queue = [];
    protected static $processing_queue = FALSE;

    protected static $processed_files = [];

    protected static $insertable = [];
    protected static $insert_count = 0;
    protected static $insert_model = NULL;

    protected static $csvescape = "\\";

    static public function processQueue() 
    {
        static::$processing_queue = TRUE;

        foreach (static::$queue as $call) {
            $callable = 'static::' . $call[0];
            call_user_func_array($callable, $call[1]);
        }
        
        static::$processing_queue = FALSE;
    }

    static public function importFileExists($file, $dir = null)
    {
        $dir = ($dir) ? $dir : dirname(__FILE__) . '/../../database/dumps';
        $path = $dir . '/' . $file;
        return file_exists($path);
    }

    static public function importSqlFile($file, $dir = NULL, $db_table = NULL) 
    {
        $default_dir = ($dir) ? FALSE : TRUE;
        $dir = ($dir) ? $dir : dirname(__FILE__) . '/../../database/dumps';
        $path = $dir . '/' . $file;
        $prefix = DB::getTablePrefix();
        $display_path = ($default_dir) ? '<app_dir>/database/dumps/' . $file : $path;

        if(static::$use_queue && !static::$processing_queue) {
            static::$queue[$path] = [ 'importSqlFile', func_get_args() ];
            return;
        }

        if(array_key_exists($path, static::$processed_files)) {
            return; // Already processed, exiting
        }

        static::$processed_files[$path] = TRUE;

        if($db_table && Schema::hasTable($db_table)) {
            DB::table($db_table)->truncate();
        }

        if(!is_file($path)) {
            throw new \Exception('Warning: Sql import file not found: ' . $display_path);
            return;
        }

        $contents = file($path, FILE_SKIP_EMPTY_LINES);

        foreach($contents as $line) {
            $line = trim($line);

            if(empty($line) || $line[0] == '/' || $line[0] == '*') {
                continue; // Ignore comments
            }

            try {
                $line = sprintf($line, $prefix);
            }
            catch (\ErrorException $ex) {
                $line = str_replace('`%s', '`' . $prefix, $line);
            }

            try {
                \DB::insert($line);
            }
            catch (Illuminate\Database\QueryException $ex) {
                // Ignore db errors?
                //echo $ex->getMessage() . PHP_EOL . PHP_EOL;
            }
        }
    }

    static public function exportCSV($file, $map, $model_class, $id_field = 'id', $dir = NULL, $overwrite = false)
    {
        $default_dir = ($dir) ? FALSE : TRUE;
        $dir = ($dir) ? $dir : dirname(__FILE__) . '/../../database/dumps';
        $path = $dir . '/' . $file;
        $display_path = ($default_dir) ? '<app_dir>/database/dumps/' . $file : $path;
        $file_exists = is_file($path);

        if(!$overwrite) {
            if($file_exists) {
                throw new \Exception('CSV export file already exists: ' . $display_path);
                return false;
            }
        } else {
            if($file_exists && !is_writable($path)) {
                throw new \Exception('CSV export file is not writeable: ' . $display_path);
                return false;
            }
        }

        $fp = fopen($path, 'w'); // w option truncates file to 0 length
        fputcsv($fp, $map, escape: static::$csvescape); // use map as header row in CSV
        $csvescape = static::$csvescape;

        $model_class::chunk(500, function(Collection $Objects) use ($fp, $map, $csvescape) {
            foreach($Objects as $Object) {
                $raw = $Object->attributesToArray();
                $mapped = [];

                foreach($map as $mkey => $lr) {
                    $lr = explode('|', $lr);
                    $l = $lr[0];
                    $format = array_key_exists(1, $lr) ? $lr[1] : 'null';

                    if(array_key_exists($l, $raw)) {
                        $val = $raw[$l];

                        switch($format) {
                            case 'boolstr':
                                $val = $val && $val != 'no' && $val != 'false' ? 'yes' : 'no';
                                break;
                            default:    
                                $val = $val ?: '';
                        }
                    }
                    else {
                        $val = '';
                    }

                    $mapped[] = $val;
                }

                fputcsv($fp, $mapped, escape: $csvescape);
            }
        });

        fclose($fp);
        return true;
    }

    static public function importCSV($file, $map, $model_class, $id_field = 'id', $dir = NULL, $direct_insert_threshold = 100) 
    {
        $default_dir = ($dir) ? FALSE : TRUE;
        $dir = ($dir) ? $dir : dirname(__FILE__) . '/../../database/dumps';
        $path = $dir . '/' . $file;
        $display_path = ($default_dir) ? '<app_dir>/database/dumps/' . $file : $path;

        if(static::$use_queue && !static::$processing_queue) {
            static::$queue[$path] = [ 'importCSV', func_get_args() ];
            return;
        }

        if(array_key_exists($path, static::$processed_files)) {
            return; // Already processed, exiting
        }

        static::$processed_files[$path] = TRUE;

        if(!is_file($path)) {
            throw new \Exception('Warning: CSV import file not found: ' . $display_path);
            return;
        }

        $contents = array_values(file($path, FILE_SKIP_EMPTY_LINES));
        $force_null = ($direct_insert_threshold); 

        foreach($contents as $key => $line) {
            if($key == 0) {
                continue;
            }

            try {
                $mapped = [];
                $raw = array_values(str_getcsv($line, escape: self::$csvescape));

                foreach($map as $mkey => $lr) {
                    $lr = explode('|', $lr);
                    $l = $lr[0];
                    $format = array_key_exists(1, $lr) ? $lr[1] : 'null';

                    if(array_key_exists($mkey, $raw)) {
                        $mapped[$l] = $raw[$mkey];

                        switch($format) {
                            case 'boolstr':
                                $v = strtolower($mapped[$l]);
                                $mapped[$l] = $v && $v != 'no' && $v != 'false' ? 1 : 0;
                                break;
                            default:    
                                $mapped[$l] = ($mapped[$l] == '') ? NULL : $mapped[$l]; 
                        }
                    }
                    else if($force_null) {
                        $mapped[$l] = NULL;
                    }
                }

                if(empty($mapped[$id_field])) {
                    continue;
                }

                if($direct_insert_threshold) {
                    static::_directInsert($model_class, $mapped, $direct_insert_threshold);
                    continue;
                }

                $find = [];
                $find[$id_field] = $mapped[$id_field];

                $Model = $model_class::firstOrCreate($find, $mapped);
            }
            catch (\Exception $ex) {
                // Ignore db errors?
                // echo $ex->getMessage() . PHP_EOL . PHP_EOL;
                throw $ex;
            }
        }

        static::_directInsertPush();
    }

    static protected function _directInsert($model_class, $mapped, $insert_threshold) 
    {
        static::$insertable[] = $mapped;
        static::$insert_count ++;
        static::$insert_model = $model_class;

        if(static::$insert_count >= $insert_threshold) {
            static::_directInsertPush();
        }
    }

    static protected function _directInsertPush() 
    {
        if(static::$insert_model) {
            $model_class = static::$insert_model;
            $model_class::insertOrIgnore(static::$insertable);
        }

        static::$insertable   = [];
        static::$insert_count = 0;
        static::$insert_model = NULL;
    }   

    static public function setCreatedUpdated($db_table) 
    {
        $sql_date = date('Y-m-d H:i:s');

        \DB::table($db_table)
            -> whereNull('created_at')
            -> update(['created_at' => $sql_date, 'updated_at' => $sql_date]);
    }
}
