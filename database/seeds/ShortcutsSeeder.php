<?php

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
        $languages = Config::get('bss_table_languages.shortcuts');

        if(env('IMPORT_FROM_V2', FALSE)) {
            echo('Importing Shortcuts From V2' . PHP_EOL);

            foreach($languages as $lang) {
                $v2_table = 'bible_shortcuts_' . $lang;
                $shortcuts = DB::select("SELECT * FROM {$v2_table}");
                $class_name = Shortcut::getClassNameByLanguage($lang);
                echo($lang . ' ');

                foreach($shortcuts as $sc) {
                    unset($sc->index);
                    $sc->display = ($sc->display == 'yes') ? 1 : 0;
                    $class_name::create( get_object_vars($sc) );
                }
            }

            echo(PHP_EOL);
        }
        else {
            // todo - import book lists from files
        }
    }
}
