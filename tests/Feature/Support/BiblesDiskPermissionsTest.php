<?php

namespace Tests\Feature\Support;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;

/**
 * The 'bibles' disk must not take directories private.
 *
 * Flysystem's local adapter chmods a directory makeDirectory() is called on even when it
 * already exists, to the disk's default directory visibility - private (0700) unless the disk
 * says otherwise.  ImporterAbstract calls makeDirectory('unofficial') before storing an upload,
 * so every import (and the import tests, run as the directory's owner) took bibles/unofficial
 * to 0700, and the web server could no longer read it: the Bibles page broke on its next scan.
 */
class BiblesDiskPermissionsTest extends TestCase
{
    private string $relative;
    private string $path;

    public function setUp(): void
    {
        parent::setUp();

        $this->relative = 'misc/perm_test_' . bin2hex(random_bytes(4));
        $this->path     = base_path('bibles/' . $this->relative);
    }

    public function tearDown(): void
    {
        is_dir($this->path) && rmdir($this->path);

        parent::tearDown();
    }

    private function mode(): int
    {
        clearstatcache(TRUE, $this->path);
        return fileperms($this->path) & 0777;
    }

    public function testMakeDirectoryLeavesAnExistingDirectoryReadable(): void
    {
        mkdir($this->path, 0775, TRUE);
        chmod($this->path, 0775);

        Storage::disk('bibles')->makeDirectory($this->relative);

        $this->assertSame(0775, $this->mode(), sprintf('Existing directory changed to %o', $this->mode()));
    }

    public function testNewDirectoriesAreGroupAccessible(): void
    {
        Storage::disk('bibles')->makeDirectory($this->relative);

        $this->assertSame(0775, $this->mode() & 0775, sprintf('New directory created as %o', $this->mode()));
    }
}
