<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneImportFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bibles:prune-imports {--days=7 : Delete files older than this many days} {--dry-run : List what would be deleted without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes abandoned Bible import uploads left behind by import checks that were never completed.';

    /**
     * Importer directories (under bibles/) that hold nothing but transient
     * uploads and are therefore safe to prune.
     *
     * Deliberately excludes 'unofficial' and 'modules': the BibleSuperSearch
     * importer uploads into 'unofficial', which is also where installed
     * unofficial module archives live, so pruning it could delete a real
     * module. 'rendered' is managed separately by RenderManager.
     *
     * @var array<int, string>
     */
    protected $prunable_dirs = ['mysword', 'usfm', 'unbound', 'evening', 'analyzer', 'misc'];

    /**
     * Filenames that are part of the repository rather than uploads, and must
     * never be pruned. Compared case-insensitively.
     *
     * @var array<int, string>
     */
    protected $keep_files = ['readme.txt', 'readme.md', 'index.html', 'index.php', '.gitignore', '.gitkeep'];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');

        if($days < 1) {
            $this->error('--days must be at least 1');
            return 1;
        }

        $dry_run = (bool) $this->option('dry-run');
        $cutoff = time() - ($days * 86400);
        $base = base_path('bibles') . DIRECTORY_SEPARATOR;
        $deleted = $bytes = 0;

        foreach($this->prunable_dirs as $short) {
            $dir = realpath($base . $short);

            if($dir === FALSE || !is_dir($dir)) {
                continue;
            }

            foreach(scandir($dir) as $entry) {
                if($entry === '.' || $entry === '..') {
                    continue;
                }

                $path = $dir . DIRECTORY_SEPARATOR . $entry;

                // Never follow a link out of the directory, and skip anything
                // that is not a plain file (e.g. .gitignore is kept by name).
                if(is_link($path) || !is_file($path)) {
                    continue;
                }

                if(in_array(strtolower($entry), $this->keep_files, TRUE)) {
                    continue;
                }

                if(filemtime($path) > $cutoff) {
                    continue;
                }

                $size = filesize($path);
                $this->line(($dry_run ? '[dry run] ' : '') . 'Removing ' . $short . '/' . $entry);

                if(!$dry_run && !unlink($path)) {
                    $this->error('Could not delete ' . $path);
                    continue;
                }

                $deleted++;
                $bytes += $size;
            }
        }

        $this->info(sprintf(
            '%s %d abandoned import file(s), %s',
            $dry_run ? 'Would remove' : 'Removed',
            $deleted,
            $this->formatBytes($bytes)
        ));

        return 0;
    }

    /**
     * @param  int  $bytes
     * @return string
     */
    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }
}
