<?php

use Illuminate\Database\Seeder;

class BibleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        $supported = Config::get('supported_bibles');

        print_r($supported);

        foreach($supported as $bible) {
            //Bible::create($bible);
        }
    }
}
