<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Bible;

class BibleOfficial extends BibleAbstract
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bible:official';

    /**
     * The console command description.
     */
    protected $description = 'DEV MODE: Sets Bible module as official';

    protected function _handleSingleBible(Bible $Bible) {
        $Bible->official = 1;
        $Bible->save();

        print "Please run `php artisan bible:migrate-module-files` \n\n";
    }
}
