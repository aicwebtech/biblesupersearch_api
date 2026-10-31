<?php

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestCleanupExtension;

/**
 * The run lock that keeps the cleanup sweep from running while another test run is in progress.
 * flock() locks belong to the open file, so two handles in this one process stand in for two runs.
 */
class TestCleanupExtensionTest extends TestCase
{
    private string $path = '';

    /**
     * @var array<int, resource>
     */
    private array $handles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/bss-test-lock-' . bin2hex(random_bytes(6)) . '.lock';
    }

    protected function tearDown(): void
    {
        foreach($this->handles as $handle) {
            fclose($handle);
        }

        if(is_file($this->path)) {
            chmod($this->path, 0600);
            unlink($this->path);
        }

        parent::tearDown();
    }

    /** @return resource */
    private function open()
    {
        return $this->handles[] = fopen($this->path, 'c');
    }

    public function testTheOnlyRunMaySweep(): void
    {
        $this->assertTrue(TestCleanupExtension::takeLock($this->open()));
    }

    public function testARunStartedAlongsideAnotherDoesNotSweep(): void
    {
        $First = $this->open();
        $this->assertTrue(TestCleanupExtension::takeLock($First));

        // The first run has swept, and holds the lock shared for the rest of its run
        flock($First, LOCK_SH);

        $this->assertFalse(TestCleanupExtension::takeLock($this->open()), 'Swept alongside a running run');
        $this->assertFalse(TestCleanupExtension::takeLock($this->open()), 'A third run swept');
    }

    public function testARunAfterTheOthersEndedMaySweep(): void
    {
        $First = $this->open();
        TestCleanupExtension::takeLock($First);
        flock($First, LOCK_SH);
        flock($First, LOCK_UN);

        $this->assertTrue(TestCleanupExtension::takeLock($this->open()));
    }

    public function testALockFileThatCannotBeWrittenIsOpenedReadOnly(): void
    {
        if(function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root can write a read-only file');
        }

        touch($this->path);
        chmod($this->path, 0444);

        $lock = TestCleanupExtension::openLock($this->path);
        $this->assertIsResource($lock);
        $this->handles[] = $lock;

        $this->assertTrue(TestCleanupExtension::takeLock($lock));

        // Still a lock other runs see
        flock($lock, LOCK_SH);
        $this->assertFalse(TestCleanupExtension::takeLock($this->handles[] = TestCleanupExtension::openLock($this->path)));
    }

    public function testALockFileThatCannotBeOpenedAtAllIsNull(): void
    {
        $this->assertNull(TestCleanupExtension::openLock(sys_get_temp_dir() . '/bss-no-such-dir-' . bin2hex(random_bytes(6)) . '/run.lock'));
    }
}
