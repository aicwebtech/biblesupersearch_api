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
                Bible::create($bible); 
            }
            catch(Exception $e) {
                //var_dump($e);
            } 
        }

        if(env('IMPORT_FROM_V2', FALSE)) {
            echo('Importing Bibles From V2' . PHP_EOL);
        }
    }
}
