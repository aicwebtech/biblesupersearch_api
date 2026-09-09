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
}
