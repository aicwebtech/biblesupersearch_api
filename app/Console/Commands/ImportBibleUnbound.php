<?php

namespace aicwebtech\BibleSuperSearch\Console\Commands;

use Illuminate\Console\Command;
use aicwebtech\BibleSuperSearch\Importers\Unbound;

class ImportBibleUnbound extends ImportBible {
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $name = 'Unbound Bible';
    protected $signature = 'bible:import-unbound';
    protected $import_dir = 'unbound';
    protected $file_extension = 'zip';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Import a Bible from in a (zipped) Unbound Bible Format
                              These can be downloaded at
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
        $Importer = new Unbound();
        $this->_handleHelper($Importer);
    }
}
