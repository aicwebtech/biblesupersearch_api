<?php

use Illuminate\Database\Seeder;
use App\Models\Bible;

class Bibles extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $supported = Config::get('bss_supported_bibles');
        $lang = env('DEFAULT_LANGUAGE','English');
        $lang_st = env('DEFAULT_LANGUAGE_SHORT','en');
        $rank = 0;

        foreach($supported as $bible) {
            $bible['shortname']     = (isset($bible['shortname']))  ? $bible['shortname'] : ucfirst($bible['module']);
            $bible['lang']          = (isset($bible['lang']))       ? $bible['lang'] : $lang;
            $bible['lang_short']    = (isset($bible['lang_short'])) ? $bible['lang_short'] : $lang_st;
            $rank += 10;
            $bible['rank'] = $rank;
            
            // Ignore duplicate keys
            try {
                Bible::forceCreate($bible); 
            }
            catch(Exception $e) {
                //var_dump($e);
            } 
        }

        if(env('IMPORT_FROM_V2', FALSE)) {
            echo('Importing Bibles From V2' . PHP_EOL);

            $bibles_v2 = DB::select('SELECT * FROM bible_versions'); // bible_versions - list of all INSTALLED Bibles

            foreach($bibles_v2 as $v2) {
                $module = $v2->shortname;
                
                // workaround for the Reina Valera Bibles
                if( strpos($module, 'rv') ) {
                    $module = 'rv_' . intval($module);
                }

                $Bible = Bible::firstOrNew([ 'module' => $module ]);

                $Bible->description = $v2->description;

                if(!$Bible->exists) {
                    $rank += 10;
                    $Bible->name        = $v2->fullname;
                    $Bible->module      = $module;
                    $Bible->shortname   = ucfirst($v2->shortname);
                    $Bible->shortname   = ucfirst($v2->shortname);
                    $Bible->lang        = $v2->language;
                    $Bible->lang_short  = $v2->language_short;
                    $Bible->italics     = ($v2->italics == 'yes') ? 1 : 0;
                    $Bible->strongs     = ($v2->strongs == 'yes') ? 1 : 0;
                    $Bible->rank        = $rank;
                }

                $Bible->save();
                $Bible->install();
            }
        }
    }
}
