<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Importers\Unbound;

// Note: Comment out in Kernel when not actively using.
// Not intended for the public's use
class ImportBibleCustom extends ImportBible 
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'bible:import-custom {importer}';
    protected $import_dir = 'misc';
    protected $valid = ['rvg', 'irv', 'usfm', 'text'];

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Used for custom Bible imports, ususally scripts written to import a single Bible.  Not intended for public use.';
    protected $require_file = FALSE;

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
        $importer = $this->argument('importer');
        $class_name = 'App\Importers\\' . ucfirst($importer);

        if(!in_array($importer, $this->valid)) {
            throw new \Exception('Not a valid importer: ' . $importer);
        }

        if(!class_exists($class_name)) {
            throw new \Exception('Importer Class does not Exist: ' . $class_name);
        }

        $Importer = new $class_name();
        $this->_handleHelper($Importer);
    }
}
