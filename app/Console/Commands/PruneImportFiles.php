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
    protected $prunable_dirs = ['mysword', 'mybible', 'usfm', 'unbound', 'evening', 'analyzer', 'misc'];

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
        $base = realpath(base_path('bibles'));
        $deleted = $bytes = 0;

        if($base === FALSE) {
            $this->error('Could not resolve the bibles directory');

            return 1;
        }

        foreach($this->prunable_dirs as $short) {
            $expected = $base . DIRECTORY_SEPARATOR . $short;

            // A prunable directory that is itself a symlink would have realpath()
            // resolve to wherever it points, and the per-file is_link() check below
            // cannot help: the files inside that target are ordinary files, so the
            // command would happily delete somebody else's data. Refuse to follow it,
            // and say so rather than skipping silently.
            if(is_link($expected)) {
                $this->error('Skipping ' . $short . ': the directory is a symlink');

                continue;
            }

            $dir = realpath($expected);

            if($dir === FALSE || !is_dir($dir)) {
                continue;
            }

            // Belt and braces: with no link on the directory itself, realpath() must
            // return the canonical path unchanged. Anything else means a link higher
            // up resolved the scan outside the bibles root.
            if($dir !== $expected) {
                $this->error('Skipping ' . $short . ': resolves outside the bibles directory');

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

                $mtime = $this->getFileMtime($path);

                // An unreadable timestamp says nothing about the file's age, and FALSE
                // does not compare as "newer than the cutoff" -- it would fall straight
                // through to the unlink() below and delete a file of unknown age. Skip
                // it instead: leaving one stale upload behind is always cheaper than
                // deleting something that should have been kept.
                if($mtime === FALSE) {
                    $this->error('Could not read the modification time of ' . $path . '; skipping');
                    continue;
                }

                if($mtime > $cutoff) {
                    continue;
                }

                // Only feeds the summary total, so a failure here is cosmetic.
                $size = @filesize($path);
                $size = ($size === FALSE) ? 0 : $size;

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
     * Modification time of $path, or FALSE if it cannot be read.
     *
     * Wrapped rather than called inline so the failure path -- a race against the
     * file being removed between scandir() and here -- can be exercised by a test.
     * Suppressed because the FALSE return is handled by the caller; compare
     * InstallManager::installLockIsStale().
     *
     * @param  string  $path
     * @return int|false
     */
    protected function getFileMtime(string $path)
    {
        return @filemtime($path);
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
