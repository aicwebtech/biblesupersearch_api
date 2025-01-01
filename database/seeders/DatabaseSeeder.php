<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use \DB;

// This isn't autoloading??
//require_once(dirname(__FILE__) . '/UserTableSeeder.php');

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        // To do - move these 'seeds' to respective db table create migrations
        // 'Seeds' are intended to fill db with test data,
        // NOT populating with data needed for the application to work
        // As we are doing here

        // TODO - add artisan command for adding admin user
        //  $this->call(UserTableSeeder::class); // do not use
        //// $this->call('Bibles'); // No longer used, do NOT uncomment
        // $this->call('IndexTableSeeder'); // No longer used

        // $this->call(BookListSeeder::class);
        // $this->call(ShortcutsSeeder::class);

        //// $this->call('StrongsDefinitionsSeeder'); // moved to migration, do NOT uncomment

        Model::reguard();
    }

    static public function importSqlFile($file, $dir = NULL, $threshold = 100) 
    {
        $default_dir = ($dir) ? FALSE : TRUE;
        $dir = ($dir) ? $dir : dirname(__FILE__) . '/../dumps';

        $prefix = DB::getTablePrefix();
        $path = $dir . '/' . $file;
        $display_path = ($default_dir) ? '<app_dir>/database/dumps/' . $file : $path;

        if(!is_file($path)) {
            echo 'Warning: Sql import file not found, continuing: ' . $display_path . PHP_EOL;
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

        DB::table($db_table)
            -> whereNull('created_at')
            -> update(['created_at' => $sql_date, 'updated_at' => $sql_date]);
    }
}
