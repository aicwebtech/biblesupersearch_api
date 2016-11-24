<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Importers\Unbound;

class ImportBibleUnbound extends ImportBible {
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'bible:import:unbound';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Import a Bible from a UTF-8 text file in the Unbound Bible Format
                              https://unbound.biola.edu/index.cfm?method=downloads.showDownloadMain';

    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle() {
        //
        var_dump('here');
        
        $Importer = new Unbound();
        $Importer->import();
    }
}
