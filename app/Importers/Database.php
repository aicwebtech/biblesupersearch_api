<?php
namespace App\Importers;

/*
 * Generic importer for importing database dumps
 */

class Database {

    static public function importSqlFile($file, $dir = NULL) {
        $default_dir = ($dir) ? FALSE : TRUE;
        $dir = ($dir) ? $dir : dirname(__FILE__) . '/../../database/dumps';
        $prefix = config('database.prefix');
        $path = $dir . '/' . $file;
        $display_path = ($default_dir) ? '<app_dir>/database/dumps/' . $file : $path;
        //var_dump($file);

        if(!is_file($path)) {
            echo 'Warning: Sql import file not found: ' . $display_path . PHP_EOL;
            return;
        }

        $contents = file($path, FILE_SKIP_EMPTY_LINES);

        foreach($contents as $line) {
            $line = trim($line);

            if(empty($line) || $line{0} == '/' || $line{0} == '*') {
                continue; // Ignore comments
            }

            try {
                $line = sprintf($line, $prefix);
            }
            catch (\ErrorException $ex) {
                $line = str_replace('`%s', '`' . $prefix, $line);
            }
            //echo $line . PHP_EOL;

            try {
                \DB::insert($line);
            }
            catch (Illuminate\Database\QueryException $ex) {
                // Ignore db errors?
                //echo $ex->getMessage() . PHP_EOL . PHP_EOL;
            }
        }
    }

    static public function setCreatedUpdated($db_table) {
        $sql_date = date('Y-m-d H:i:s');

        \DB::table($db_table)
            -> whereNull('created_at')
            -> update(['created_at' => $sql_date, 'updated_at' => $sql_date]);
    }
}