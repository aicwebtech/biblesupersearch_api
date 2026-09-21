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
     * Subdirectory, inside each importer directory, that holds nothing but HTTP
     * uploads -- the only thing this command may delete.
     *
     * Nothing else under bibles/ is touched, and the directories themselves are
     * never listed here. An importer directory doubles as the place an operator is
     * told to put a source file: every CLI import command prints its own path
     * ("Files to be imported need to be placed in .../bibles/unbound"), and
     * App\Importers\Text reads a fixed bibles/misc/TEXT-PCE.txt. Pruning those
     * directories deleted operator-placed sources -- and, for 'unofficial',
     * installed module archives -- after a week, because nothing on disk said
     * which files had arrived over HTTP. ImporterAbstract::getUploadDir() now
     * keeps uploads apart, and this walks whatever importer directories exist
     * rather than a list that the next new importer would be missing from.
     *
     * @var string
     */
    protected $upload_dir_short = 'uploads';

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
        $base = realpath($this->getBiblesPath());
        $deleted = $bytes = 0;

        if($base === FALSE) {
            $this->error('Could not resolve the bibles directory');

            return 1;
        }

        foreach($this->getUploadDirectories($base) as $short => $dir) {
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
     * Upload directories to prune, as importer-relative name => canonical path.
     *
     * Discovered rather than listed: bibles/mybible was missed when this command was
     * written because the directory only appears once somebody has uploaded a MyBible
     * module, and the same would be true of the next importer added.
     *
     * Nothing here follows a symlink. realpath() resolves one silently, and the
     * per-file is_link() check in the caller cannot help afterwards: the files inside
     * the target are ordinary files, so the command would delete somebody else's data.
     * Both the importer directory and its uploads subdirectory are checked, and a link
     * is reported rather than skipped in silence.
     *
     * @param  string  $base  Canonical path to the bibles directory
     * @return array<string, string>
     */
    protected function getUploadDirectories(string $base): array
    {
        $found = [];

        foreach(scandir($base) as $entry) {
            if($entry === '.' || $entry === '..') {
                continue;
            }

            $importer_dir = $base . DIRECTORY_SEPARATOR . $entry;

            if(is_link($importer_dir)) {
                // Only worth saying for a directory that actually holds uploads;
                // bibles/ has other tenants (modules, rendered, audio).
                if(is_dir($importer_dir . DIRECTORY_SEPARATOR . $this->upload_dir_short)) {
                    $this->error('Skipping ' . $entry . ': the importer directory is a symlink');
                }

                continue;
            }

            if(!is_dir($importer_dir)) {
                continue;
            }

            $short    = $entry . '/' . $this->upload_dir_short;
            $expected = $importer_dir . DIRECTORY_SEPARATOR . $this->upload_dir_short;

            if(is_link($expected)) {
                $this->error('Skipping ' . $short . ': the directory is a symlink');

                continue;
            }

            $dir = realpath($expected);

            if($dir === FALSE || !is_dir($dir)) {
                continue;
            }

            // Belt and braces: with no link on either directory, realpath() must return
            // the canonical path unchanged. Anything else means a link higher up
            // resolved the scan outside the bibles root.
            if($dir !== $expected) {
                $this->error('Skipping ' . $short . ': resolves outside the bibles directory');

                continue;
            }

            $found[$short] = $dir;
        }

        return $found;
    }

    /**
     * Root directory holding the importer directories.
     *
     * A seam for tests: the symlink-escape case has to replace a prunable directory
     * with a link, which must never be done to the real bibles/ tree -- those
     * directories are shared with other tests and bibles/misc/readme.txt is tracked
     * in the repository.
     *
     * @return string
     */
    protected function getBiblesPath(): string
    {
        return base_path('bibles');
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
