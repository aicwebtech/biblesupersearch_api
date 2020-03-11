<?php

namespace aicwebtech\BibleSuperSearch\Console\Commands;

use Illuminate\Console\Command;
use aicwebtech\BibleSuperSearch\Models\Bible;

class MigrateModuleFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bible:migrate-module-files {--dry-run} {--silent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensures Official and Unoffical Bible module files are in the correct directories';

    protected $map = [
        'No changes needed',
        'Deleted extra file',
        'Moved file',
        'No module file(s) exist'
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $dry_run = $this->option('dry-run');
        $silent  = $this->option('silent');

        $Bibles = Bible::orderBy('rank')->get();
        $module_len = 25;
        $name_len = 0;

        foreach($Bibles as $Bible) {
            $module_len = max($module_len, strlen($Bible->module));
            $name_len = max($name_len, strlen($Bible->name));
        }

        if(!$silent) {
            print '' . PHP_EOL;
            print 'Migrating Bible Module Files' . PHP_EOL;

            if($dry_run) {
                print PHP_EOL . ' ===> DRY RUN' . PHP_EOL . PHP_EOL;
            }

            print '' . PHP_EOL;
            print "\t" . str_pad('Module', $module_len) .  "  Official  Errors  Code  " . str_pad('Response', 25) . '  ' . str_pad('Name', $name_len) . PHP_EOL;
            print "\t" . str_repeat('-', $module_len + $name_len + 56) . PHP_EOL;
        }

        Bible::populateBibleTable();

        foreach($Bibles as $Bible) {
            $mig = $Bible->migrateModuleFile($dry_run);

            if($silent) {
                continue;
            }

            $suc = !$mig ? 'Yes' : 'No';
            $off = ($Bible->official) ? 'Yes' : 'No';
            $code = $Bible->migrate_code;

            $text = "\t" . str_pad($Bible->module, $module_len);
            $text .=  "  " . str_pad($off, 8) . "  " . str_pad($suc, 6) .  "  " . str_pad($code, 4) . '  ' . str_pad($this->map[$code], 25);
            $text .= '  ' . str_pad($Bible->name, $name_len);

            $text .= PHP_EOL;

            print $text;
        }

        if(!$silent) {
            print '' . PHP_EOL;
        }

        return;
    }
}
