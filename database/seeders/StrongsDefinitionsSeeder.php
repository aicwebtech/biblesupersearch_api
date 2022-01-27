<?php

namespace Database\Seeders;

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
        return; // Obsolete

        // Import from file
        $file  = 'strongs_definitions_en.sql';
        $table = 'strongs_definitions';
        DatabaseSeeder::importSqlFile($file);
        DatabaseSeeder::setCreatedUpdated($table);
    }

}
