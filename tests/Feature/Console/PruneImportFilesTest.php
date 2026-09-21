<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Abandoned preflight uploads accumulated forever: nothing deleted a file whose
 * import was checked but never completed.
 *
 * The command used to walk the importer directories themselves, which made it
 * delete far more than abandoned uploads: those same directories are where the
 * CLI import commands tell an operator to put a source file, and where
 * App\Importers\Text reads its fixed bibles/misc/TEXT-PCE.txt from. Uploads now
 * live in a dedicated bibles/<importer>/uploads subdirectory, and that is the
 * only thing this command may touch.
 */
class PruneImportFilesTest extends TestCase
{
    protected function importDir(string $short): string
    {
        return base_path('bibles') . DIRECTORY_SEPARATOR . $short . DIRECTORY_SEPARATOR;
    }

    protected function uploadDir(string $short): string
    {
        return $this->importDir($short) . 'uploads' . DIRECTORY_SEPARATOR;
    }

    protected function removeIfPresent(string $path): void
    {
        if(is_link($path) || file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * Every importer that accepts an upload must have its upload directory pruned.
     *
     * Asserted against the importers themselves rather than a hardcoded list: the
     * old list was missing bibles/mybible, whose directory only appears once
     * somebody has uploaded a MyBible module, so abandoned uploads there
     * accumulated forever. The command discovers the directories now, and this
     * pins that discovery against every importer in the tree.
     */
    public function testEveryImporterUploadDirectoryIsDiscovered(): void
    {
        $Command = new \App\Console\Commands\PruneImportFiles();
        $Command->setLaravel(app());

        $method = new \ReflectionMethod($Command, 'getUploadDirectories');
        $found = $method->invoke($Command, realpath(base_path('bibles')));

        foreach($this->concreteImporters() as $class => $Importer) {
            if(!$this->usesUploadSubdirectory($class)) {
                continue;
            }

            $upload_dir = realpath($Importer->getUploadDir());

            $this->assertNotFalse($upload_dir, $class . ': bibles/' . $this->pathShort($class) . '/uploads does not exist');

            $this->assertContains(
                $upload_dir,
                $found,
                $class . ' uploads into ' . $upload_dir . ', which is never pruned'
            );
        }
    }

    /**
     * The other half of the rule: an importer that opts out of the uploads
     * subdirectory keeps its files, and the pruner must not reach them.
     *
     * App\Importers\BibleSuperSearch stores into bibles/unofficial itself, because
     * what it accepts is the installed module archive that Bible::install() reads on
     * every reinstall, not a source file the commit step consumes. Pruning that
     * directory would delete installed Bibles a week after they were imported.
     */
    public function testAnImporterThatOptsOutIsNeverPruned(): void
    {
        $Command = new \App\Console\Commands\PruneImportFiles();
        $Command->setLaravel(app());

        $method = new \ReflectionMethod($Command, 'getUploadDirectories');
        $found = $method->invoke($Command, realpath(base_path('bibles')));

        $opted_out = 0;

        foreach($this->concreteImporters() as $class => $Importer) {
            if($this->usesUploadSubdirectory($class)) {
                continue;
            }

            $opted_out++;
            $upload_dir = realpath($Importer->getUploadDir());

            $this->assertSame(
                realpath($Importer->getImportDir()),
                $upload_dir,
                $class . ' must store into its own directory'
            );

            $this->assertNotContains(
                $upload_dir,
                $found,
                $class . ' stores kept files in ' . $upload_dir . ', which must never be pruned'
            );
        }

        $this->assertSame(1, $opted_out, 'Precondition: BibleSuperSearch is the one importer that opts out');
    }

    /**
     * A fresh checkout needs the directory to exist: the upload is stored through the
     * 'bibles' disk, and the pruner only finds directories that are already there.
     */
    public function testEveryImporterUploadDirectoryIsInTheRepository(): void
    {
        foreach($this->concreteImporters() as $class => $Importer) {
            if(!$this->usesUploadSubdirectory($class)) {
                continue;
            }

            $this->assertFileExists(
                $Importer->getUploadDir() . '.gitkeep',
                $class . ': bibles/' . $this->pathShort($class) . '/uploads must be tracked'
            );
        }
    }

    /**
     * Every concrete importer, keyed by class name.
     *
     * @return array<string, \App\Importers\ImporterAbstract>
     */
    protected function concreteImporters(): array
    {
        $importers = [];

        foreach(glob(app_path('Importers') . '/*.php') as $file) {
            $class = 'App\\Importers\\' . basename($file, '.php');

            // app/Importers also holds helpers that are not importers at all.
            if(!class_exists($class) || !is_subclass_of($class, \App\Importers\ImporterAbstract::class)) {
                continue;
            }

            if((new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $importers[$class] = new $class();
        }

        $this->assertNotEmpty($importers, 'Precondition: importers were found');

        return $importers;
    }

    /**
     * @param  string  $class
     * @return string
     */
    protected function pathShort(string $class): string
    {
        return (new \ReflectionClass($class))->getDefaultProperties()['path_short'] ?? '';
    }

    /**
     * Whether $class stages uploads in a prunable subdirectory, or opts out and keeps
     * them in its own directory.
     *
     * @param  string  $class
     * @return bool
     */
    protected function usesUploadSubdirectory(string $class): bool
    {
        return (string) ((new \ReflectionClass($class))->getDefaultProperties()['upload_dir_short'] ?? '') !== '';
    }

    /**
     * An old upload is removed; a recent one is kept.
     */
    public function testRemovesOnlyOldFiles(): void
    {
        $dir = $this->uploadDir('mysword');
        $old = $dir . 'prune_old_' . bin2hex(random_bytes(4)) . '.mybible';
        $new = $dir . 'prune_new_' . bin2hex(random_bytes(4)) . '.mybible';

        file_put_contents($old, 'old');
        file_put_contents($new, 'new');
        touch($old, time() - (30 * 86400));

        try {
            $this->artisan('bibles:prune-imports', ['--days' => 7])->assertExitCode(0);

            $this->assertFileDoesNotExist($old, 'Stale upload should be pruned');
            $this->assertFileExists($new, 'Recent upload must be kept');
        }
        finally {
            $this->removeIfPresent($old);
            $this->removeIfPresent($new);
        }
    }

    /**
     * The regression this directory split exists for. An importer directory holds
     * source files an operator placed there by hand -- every CLI import command prints
     * its own path ("Files to be imported need to be placed in .../bibles/unbound"),
     * and App\Importers\Text reads bibles/misc/TEXT-PCE.txt -- which are kept for as
     * long as the operator wants them, not seven days.
     */
    public function testNeverTouchesImporterSourceDirectories(): void
    {
        $sources = [];

        foreach(['mysword' => '.mybible', 'misc' => '.txt', 'unbound' => '.zip', 'analyzer' => '.bib'] as $short => $ext) {
            $path = $this->importDir($short) . 'prune_source_' . bin2hex(random_bytes(4)) . $ext;

            file_put_contents($path, 'a source file placed here by the operator');
            touch($path, time() - (365 * 86400));

            $sources[$short] = $path;
        }

        try {
            $this->artisan('bibles:prune-imports', ['--days' => 1])->assertExitCode(0);

            foreach($sources as $short => $path) {
                $this->assertFileExists($path, 'bibles/' . $short . ' holds CLI import sources and must never be pruned');
            }
        }
        finally {
            foreach($sources as $path) {
                $this->removeIfPresent($path);
            }
        }
    }

    /**
     * Installed module archives live in bibles/unofficial and bibles/modules;
     * pruning must never reach them.
     */
    public function testNeverTouchesModuleDirectories(): void
    {
        $unofficial = $this->importDir('unofficial') . 'prune_guard_' . bin2hex(random_bytes(4)) . '.zip';
        $official = $this->importDir('modules') . 'prune_guard_' . bin2hex(random_bytes(4)) . '.zip';

        file_put_contents($unofficial, 'module');
        file_put_contents($official, 'module');
        touch($unofficial, time() - (365 * 86400));
        touch($official, time() - (365 * 86400));

        try {
            $this->artisan('bibles:prune-imports', ['--days' => 1])->assertExitCode(0);

            $this->assertFileExists($unofficial, 'bibles/unofficial must never be pruned');
            $this->assertFileExists($official, 'bibles/modules must never be pruned');
        }
        finally {
            $this->removeIfPresent($unofficial);
            $this->removeIfPresent($official);
        }
    }

    /**
     * .gitkeep is what puts the upload directory in the repository, and pruning it
     * would take the directory with it on the next checkout.
     */
    public function testKeepsRepositoryFiles(): void
    {
        $gitkeep = $this->uploadDir('mysword') . '.gitkeep';

        $this->assertFileExists($gitkeep, 'Precondition: the tracked .gitkeep is present');
        touch($gitkeep, time() - (365 * 86400));

        $this->artisan('bibles:prune-imports', ['--days' => 1])->assertExitCode(0);

        $this->assertFileExists($gitkeep, 'Tracked repo files must not be pruned');
    }

    public function testDryRunDeletesNothing(): void
    {
        $dir = $this->uploadDir('mysword');
        $old = $dir . 'prune_dry_' . bin2hex(random_bytes(4)) . '.mybible';

        file_put_contents($old, 'old');
        touch($old, time() - (30 * 86400));

        try {
            $this->artisan('bibles:prune-imports', ['--days' => 7, '--dry-run' => true])->assertExitCode(0);

            $this->assertFileExists($old, 'Dry run must not delete');
        }
        finally {
            $this->removeIfPresent($old);
        }
    }

    public function testRejectsZeroDays(): void
    {
        $this->artisan('bibles:prune-imports', ['--days' => 0])->assertExitCode(1);
    }

    /**
     * Run the command with a stubbed getFileMtime(), which is otherwise only
     * reachable by racing the filesystem.
     *
     * @param  int|false  $mtime
     * @param  array  $options
     * @return array{code: int, output: string}
     */
    protected function runWithStubbedMtime($mtime, array $options = []): array
    {
        $command = new class($mtime) extends \App\Console\Commands\PruneImportFiles {
            /** @var int|false */
            private $stub_mtime;

            public function __construct($stub_mtime)
            {
                $this->stub_mtime = $stub_mtime;

                parent::__construct();
            }

            protected function getFileMtime(string $path)
            {
                return $this->stub_mtime;
            }
        };

        $command->setLaravel(app());

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $code = $command->run(new \Symfony\Component\Console\Input\ArrayInput($options), $output);

        return ['code' => $code, 'output' => $output->fetch()];
    }

    /**
     * filemtime() returns FALSE when the file cannot be stat'ed -- it may have been
     * removed between scandir() and the check. FALSE does not compare as "newer than
     * the cutoff", so it used to fall through to unlink() and delete a file whose age
     * was never established. A pruner must leave anything it cannot date.
     */
    public function testFileWithAnUnreadableMtimeIsNotDeleted(): void
    {
        $dir = $this->uploadDir('mysword');
        $file = $dir . 'prune_nomtime_' . bin2hex(random_bytes(4)) . '.mybible';

        file_put_contents($file, 'keep me');
        touch($file, time() - (30 * 86400)); // old enough that it would otherwise be pruned

        try {
            $result = $this->runWithStubbedMtime(FALSE, ['--days' => 1]);

            $this->assertFileExists($file, 'A file of unknown age must not be deleted');
            $this->assertStringContainsString('Could not read the modification time', $result['output']);
            $this->assertStringContainsString('Removed 0 abandoned import file(s)', $result['output']);
        }
        finally {
            $this->removeIfPresent($file);
        }
    }

    /**
     * The guard must not swallow the ordinary case: a readable, old mtime still prunes.
     */
    public function testFileWithAReadableMtimeIsStillDeleted(): void
    {
        $dir = $this->uploadDir('mysword');
        $file = $dir . 'prune_mtime_ok_' . bin2hex(random_bytes(4)) . '.mybible';

        file_put_contents($file, 'delete me');

        try {
            $result = $this->runWithStubbedMtime(time() - (30 * 86400), ['--days' => 1]);

            $this->assertFileDoesNotExist($file, 'An old file must still be pruned');
            $this->assertStringNotContainsString('Could not read the modification time', $result['output']);
        }
        finally {
            $this->removeIfPresent($file);
        }
    }

    /**
     * realpath() follows a symlinked upload directory, and the per-file is_link()
     * guard cannot help once it has: the files inside the target are ordinary files,
     * so the command would delete data that has nothing to do with imports.
     *
     * Built entirely in a scratch directory via the getBiblesPath() seam. An earlier
     * version of this test renamed a real importer directory and replaced it with a
     * link, which is unsafe twice over: those directories are shared with the other
     * tests in this class, and their readme.txt files are tracked in the repository,
     * so a crash between the rename and the cleanup would have left the working tree
     * damaged.
     */
    public function testASymlinkedUploadDirectoryIsNotFollowed(): void
    {
        $root = sys_get_temp_dir() . '/bss_prune_root_' . bin2hex(random_bytes(6));
        $outside = $root . '/outside';
        $victim = $outside . '/important.db';
        $real = $root . '/bibles/mysword/uploads';
        $stale = $real . '/abandoned.mybible';

        mkdir($outside, 0775, TRUE);
        mkdir($real, 0775, TRUE);
        mkdir($root . '/bibles/misc', 0775, TRUE);

        // Something old in a genuine upload directory, so the run is not a no-op and the
        // assertion below distinguishes "did not follow" from "did nothing".
        file_put_contents($stale, 'abandoned');
        touch($stale, time() - (30 * 86400));

        file_put_contents($victim, 'keep me');
        touch($victim, time() - (30 * 86400));

        // bibles/misc/uploads replaced by a link to $outside.
        symlink($outside, $root . '/bibles/misc/uploads');

        try {
            $result = $this->runWithBiblesPath($root . '/bibles', ['--days' => 1]);

            $this->assertFileExists($victim, 'The command escaped the bibles root via a symlink');
            $this->assertStringContainsString('misc/uploads', $result['output'], 'The skip must be reported');
            $this->assertFileDoesNotExist($stale, 'A genuine upload directory must still be pruned');
        }
        finally {
            $this->removeTree($root);
        }
    }

    /**
     * The link can just as well be one level up, on the importer directory itself, in
     * which case the uploads subdirectory inside it is somebody else's too.
     */
    public function testASymlinkedImporterDirectoryIsNotFollowed(): void
    {
        $root = sys_get_temp_dir() . '/bss_prune_root_' . bin2hex(random_bytes(6));
        $outside = $root . '/outside';
        $victim = $outside . '/uploads/important.db';
        $real = $root . '/bibles/mysword/uploads';
        $stale = $real . '/abandoned.mybible';

        mkdir($outside . '/uploads', 0775, TRUE);
        mkdir($real, 0775, TRUE);

        file_put_contents($stale, 'abandoned');
        touch($stale, time() - (30 * 86400));

        file_put_contents($victim, 'keep me');
        touch($victim, time() - (30 * 86400));

        symlink($outside, $root . '/bibles/misc');

        try {
            $result = $this->runWithBiblesPath($root . '/bibles', ['--days' => 1]);

            $this->assertFileExists($victim, 'The command escaped the bibles root via a symlinked importer directory');
            $this->assertStringContainsString('misc', $result['output'], 'The skip must be reported');
            $this->assertFileDoesNotExist($stale, 'A genuine upload directory must still be pruned');
        }
        finally {
            $this->removeTree($root);
        }
    }

    /**
     * Run the command against a scratch bibles root.
     *
     * @param  string  $path
     * @param  array  $options
     * @return array{code: int, output: string}
     */
    protected function runWithBiblesPath(string $path, array $options = []): array
    {
        $command = new class($path) extends \App\Console\Commands\PruneImportFiles {
            /** @var string */
            private $bibles_path;

            public function __construct(string $bibles_path)
            {
                $this->bibles_path = $bibles_path;

                parent::__construct();
            }

            protected function getBiblesPath(): string
            {
                return $this->bibles_path;
            }
        };

        $command->setLaravel(app());

        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $code = $command->run(new \Symfony\Component\Console\Input\ArrayInput($options), $output);

        return ['code' => $code, 'output' => $output->fetch()];
    }

    /**
     * Remove a scratch tree, links included.
     *
     * @param  string  $dir
     * @return void
     */
    protected function removeTree(string $dir): void
    {
        if(!is_dir($dir) || is_link($dir)) {
            if(is_link($dir) || file_exists($dir)) {
                unlink($dir);
            }

            return;
        }

        foreach(scandir($dir) as $entry) {
            if($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removeTree($dir . DIRECTORY_SEPARATOR . $entry);
        }

        rmdir($dir);
    }

    /**
     * Registering the command was not enough: without a schedule entry an
     * untouched install still grows unbounded, because nobody runs it by hand.
     */
    public function testCommandIsScheduled(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);

        $matches = array_filter(
            $schedule->events(),
            fn($event) => str_contains($event->command ?? '', 'bibles:prune-imports')
        );

        $this->assertCount(1, $matches, 'bibles:prune-imports must be scheduled exactly once');
    }
}
