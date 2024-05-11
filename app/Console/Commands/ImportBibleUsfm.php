<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Importers\Usfm as Importer;

class ImportBibleUsfm extends ImportBible 
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'bible:import-usfm';
    protected $import_dir = 'usfm';
    protected $file_extension = 'zip';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Import a Bible in the USFM format';

    protected $name = 'USFM .zip';
    
    /**
     * Create a new command instance.
     * @return void
     */
    public function __construct() 
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function handle() 
    {
        $Importer = new Importer();
        $this->_handleHelper($Importer);
    }
}
