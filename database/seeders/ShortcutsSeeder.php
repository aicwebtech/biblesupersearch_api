<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shortcuts\ShortcutAbstract as Shortcut;

class ShortcutsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $languages = config('bss_table_languages.shortcuts');

        foreach($languages as $lang) {
            $file  = 'shortcuts_' . $lang . '.sql';
            $table = 'shortcuts_' . $lang;
            DatabaseSeeder::importSqlFile($file);
            DatabaseSeeder::setCreatedUpdated($table);
        }
    }
}
