<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Importers\Evening as Importer;

class ImportBibleEvening extends ImportBible {
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'bible:import-evening';
    protected $import_dir = 'evening';
    protected $file_extension = '';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Import a Bible from textfiles in the obsolete \'eveningdew\' format';

    protected $name = 'Evening Dew';

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
