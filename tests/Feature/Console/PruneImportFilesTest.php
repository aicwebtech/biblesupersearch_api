<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Abandoned preflight uploads accumulated forever: nothing deleted a file whose
 * import was checked but never completed.
 *
 * The prune command must be conservative -- the BibleSuperSearch importer
 * uploads into bibles/unofficial, which also holds installed module archives,
 * so that directory (and bibles/modules) must never be touched.
 */
class PruneImportFilesTest extends TestCase
{
    protected function importDir(string $short): string
    {
        return base_path('bibles') . DIRECTORY_SEPARATOR . $short . DIRECTORY_SEPARATOR;
    }

    protected function removeIfPresent(string $path): void
    {
        if(file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * bibles/mybible was missed when the command was written, because the
     * directory is not in the repository -- it is created by the first MyBible
     * upload -- so abandoned uploads there accumulated forever.
     *
     * Asserted against the importers themselves rather than a hardcoded list,
     * so the next importer with its own directory cannot be missed either.
     */
    public function testEveryDedicatedImporterDirectoryIsPrunable(): void
    {
        $Command = new \App\Console\Commands\PruneImportFiles();
        $property = new \ReflectionProperty($Command, 'prunable_dirs');
        $prunable = $property->getValue($Command);

        foreach(glob(app_path('Importers') . '/*.php') as $file) {
            $class = 'App\\Importers\\' . basename($file, '.php');

            if(!class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $defaults = (new \ReflectionClass($class))->getDefaultProperties();
            $short = $defaults['path_short'] ?? null;

            // bibles/unofficial also holds installed module archives, so it is
            // deliberately excluded -- pruning it could delete a real module.
            if($short === null || $short === 'unofficial') {
                continue;
            }

            $this->assertContains(
                $short,
                $prunable,
                $class . ' uploads into bibles/' . $short . ', which is never pruned'
            );
        }
    }

    /**
     * An old upload is removed; a recent one is kept.
     */
    public function testRemovesOnlyOldFiles(): void
    {
        $dir = $this->importDir('mysword');
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
     * readme.txt files in the importer directories are tracked in the repo.
     */
    public function testKeepsRepositoryFiles(): void
    {
        $readme = $this->importDir('mysword') . 'readme.txt';

        $this->assertFileExists($readme, 'Precondition: repo readme is present');

        $this->artisan('bibles:prune-imports', ['--days' => 1])->assertExitCode(0);

        $this->assertFileExists($readme, 'Tracked repo files must not be pruned');
    }

    public function testDryRunDeletesNothing(): void
    {
        $dir = $this->importDir('mysword');
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
        $dir = $this->importDir('mysword');
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
        $dir = $this->importDir('mysword');
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
     * realpath() follows a symlinked prunable directory, and the per-file is_link()
     * guard cannot help once it has: the files inside the target are ordinary files,
     * so the command would delete data that has nothing to do with imports.
     *
     * bibles/misc is used because it is prunable but not tracked in the repository,
     * so it can be replaced with a link and put back.
     */
    public function testASymlinkedPrunableDirectoryIsNotFollowed(): void
    {
        $base = base_path('bibles') . DIRECTORY_SEPARATOR;
        $target = $base . 'misc';
        $outside = sys_get_temp_dir() . '/bss_prune_escape_' . bin2hex(random_bytes(4));
        $victim = $outside . '/important.db';
        $moved = $target . '_moved_' . bin2hex(random_bytes(3));

        mkdir($outside);
        file_put_contents($victim, 'keep me');
        touch($victim, time() - (30 * 86400)); // old enough to be pruned

        $existed = is_dir($target) && !is_link($target);

        try {
            if($existed) {
                rename($target, $moved);
            }

            symlink($outside, $target);

            $this->artisan('bibles:prune-imports', ['--days' => 1])->assertExitCode(0);

            $this->assertFileExists($victim, 'The command escaped the bibles directory via a symlink');
        }
        finally {
            if(is_link($target)) {
                unlink($target);
            }

            if($existed && is_dir($moved)) {
                rename($moved, $target);
            }

            $this->removeIfPresent($victim);

            if(is_dir($outside)) {
                rmdir($outside);
            }
        }
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
