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
        return; // Obsolete
//        if(config('bss.import_from_v2')) {
//            return $this->_importFromV2();
//        }

        // Import from file
        $file  = 'strongs_definitions_en.sql';
        $table = 'strongs_definitions';
        DatabaseSeeder::importSqlFile($file);
        DatabaseSeeder::setCreatedUpdated($table);
    }

    protected function _importFromV2() {
        echo('Importing Strongs Definitions From V2' . PHP_EOL);
        $prefix = DB::getTablePrefix();

        $sql = "
            INSERT INTO {$prefix}strongs_definitions (id, number, entry, created_at, updated_at)
            SELECT id, number, entry, NOW(), NOW() FROM bible_strongs
        ";

        DB::insert($sql);
    }
}
