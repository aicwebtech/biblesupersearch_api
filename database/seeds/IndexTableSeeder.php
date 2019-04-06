<?php

use Illuminate\Database\Seeder;

class IndexTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if(config('bss.import_from_v2')) {
            return $this->_importFromV2();
        }

//        DatabaseSeeder::importSqlFile('master_index.sql');
    }

    private function _importFromV2() {
        echo('Importing Master Index From V2' . PHP_EOL);
        $prefix = DB::getTablePrefix();

        $sql = "
            INSERT INTO {$prefix}master_indices (id, book, chapter, verse, standard)
            SELECT id, book, chapter, verse, 1 FROM {$prefix}verses_kjv
        ";

        DB::insert($sql);
    }
}
