<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Importers\Analyzer as Importer;

class ImportBibleAnalyzer extends ImportBible {
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'bible:import-analyzer';
    protected $import_dir = 'analyzer';
    protected $file_extension = 'bib';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Import a Bible from a SQLite database in the Bible Analyser .bib format
                              http://www.bibleanalyzer.com/download.htm';

    protected $name = 'Bible Analyser .bib';
    
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
