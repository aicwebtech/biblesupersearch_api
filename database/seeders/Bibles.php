<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bible;

// OBSOLETE!!!

class Bibles extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        return; // this is now no longer needed - doesn't import ENOUGH

        $supported = config('bss_supported_bibles');
        $lang = config('bss.defaults.language');
        $lang_st = config('bss.defaults.language_short');
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
    }

}
