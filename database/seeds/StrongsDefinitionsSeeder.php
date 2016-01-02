<?php

use Illuminate\Database\Seeder;

class StrongsDefinitionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if(env('IMPORT_FROM_V2', FALSE)) {
            echo('Importing Strongs Definitions From V2' . PHP_EOL);
            $prefix = DB::getTablePrefix();

            $sql = "
                INSERT INTO {$prefix}strongs_definitions (id, number, entry, created_at, updated_at)
                SELECT id, number, entry, NOW(), NOW() FROM bible_strongs
            ";

            DB::insert($sql);
        }
        else {
            // todo import from file
        }
    }
}
