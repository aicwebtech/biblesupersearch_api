<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Importers\MySword as Importer;

class ImportBibleMySword extends ImportBible {
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $name = 'MySword';
    protected $signature = 'bible:import-mysword';
    protected $import_dir = 'mysword';
    protected $file_extension = 'mybible';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Import a Bible in the MySword .bible format
                              NOTE: You must extract from the .gz or .zip first!
                              These can be downloaded at
                              https://mysword.info/download-mysword/bibles';

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
        $Importer = new Importer();
        $this->_handleHelper($Importer);
    }
}
