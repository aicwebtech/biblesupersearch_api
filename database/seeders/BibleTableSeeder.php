<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

// OBSOLETE!!

class BibleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        $supported = config('supported_bibles');

        print_r($supported);

        foreach($supported as $bible) {
            //Bible::create($bible);
        }
    }
}
