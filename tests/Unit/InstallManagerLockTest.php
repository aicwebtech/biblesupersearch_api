<?php

namespace Tests\Unit;

use App\InstallManager;
use PHPUnit\Framework\TestCase;

/**
 * The install claim is what makes the installer one-time.
 *
 * config('app.installed') cannot serve that purpose: install() writes it more than forty lines
 * and several minutes after the guard that reads it, and on a genuine first run there is no
 * config table to compare and set against because migrate has not run yet. The claim is an
 * atomic O_EXCL file create instead, which is exercised here against a temporary directory - the
 * helper takes an explicit path precisely so this needs no booted application.
 */
class InstallManagerLockTest extends TestCase
{
    private string $tempDir;

    public function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/bss-install-lock-' . uniqid();
        mkdir($this->tempDir, 0775, true);
    }

    public function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    public function testTheFirstClaimSucceedsAndCreatesTheLockFile(): void
    {
        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir));
        $this->assertFileExists(InstallManager::getInstallLockPath($this->tempDir));
    }

    /**
     * The point of the whole exercise: a second concurrent installer request must lose.
     */
    public function testASecondClaimFailsWhileTheFirstIsHeld(): void
    {
        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir));
        $this->assertFalse(InstallManager::claimInstallLock($this->tempDir));
        $this->assertFalse(InstallManager::claimInstallLock($this->tempDir), 'a losing claim must not take the lock either');
    }

    /**
     * A failed install has to be retryable, or one crashed attempt would wedge the installer for
     * good on a server whose owner has no shell to delete the file with.
     */
    public function testReleasingTheClaimAllowsAnotherAttempt(): void
    {
        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir));
        $this->assertTrue(InstallManager::releaseInstallLock($this->tempDir));

        $this->assertFileDoesNotExist(InstallManager::getInstallLockPath($this->tempDir));
        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir));
    }

    public function testReleasingAClaimThatWasNeverTakenIsNotAnError(): void
    {
        $this->assertTrue(InstallManager::releaseInstallLock($this->tempDir));
    }

    /**
     * install()'s finally does not run on an uncatchable fatal, and a multi-minute migration
     * invites exactly those - a max execution time, a memory_limit exhaustion, a PHP-FPM
     * request_terminate_timeout. A claim left behind by one of them must not wedge the installer
     * for good, so a claim older than the budget install() gives itself is reclaimed.
     */
    public function testAClaimOlderThanTheBudgetIsReclaimed(): void
    {
        $path = InstallManager::getInstallLockPath($this->tempDir);

        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir));
        $this->assertFalse(InstallManager::claimInstallLock($this->tempDir));

        touch($path, time() - 1201);
        clearstatcache(true, $path);

        $this->assertTrue(InstallManager::installLockIsStale($path));
        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir), 'a leaked claim must not be permanent');
    }

    /**
     * The other half of the same bargain: an install that is merely slow keeps its claim.
     */
    public function testAClaimYoungerThanTheBudgetIsHonoured(): void
    {
        $path = InstallManager::getInstallLockPath($this->tempDir);

        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir));

        touch($path, time() - 300);
        clearstatcache(true, $path);

        $this->assertFalse(InstallManager::installLockIsStale($path));
        $this->assertFalse(InstallManager::claimInstallLock($this->tempDir));
    }

    /**
     * A claim that could not be created at all - an unwritable storage/app, a full disk - looks
     * exactly like a lost race to the caller. The presence of the file is what tells them apart,
     * so that a permissions problem is not reported as "an installation is already running".
     */
    public function testThePresenceOfAClaimIsReportedSeparatelyFromTakingOne(): void
    {
        $this->assertFalse(InstallManager::installLockExists($this->tempDir));

        $this->assertTrue(InstallManager::claimInstallLock($this->tempDir));
        $this->assertTrue(InstallManager::installLockExists($this->tempDir));

        InstallManager::releaseInstallLock($this->tempDir);
        $this->assertFalse(InstallManager::installLockExists($this->tempDir));
    }

    /**
     * A claim cannot be taken in a directory that does not admit one, and the failure must not
     * be mistaken for the claim being held by somebody else.
     */
    public function testAClaimCannotBeTakenInAnUnwritableDirectory(): void
    {
        if(function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $this->markTestSkipped('root ignores the directory permissions this case turns on');
        }

        $readonly = $this->tempDir . '/readonly';

        mkdir($readonly, 0500, true);

        // claimInstallLock() suppresses the failed open with @, but PHPUnit's own error handler
        // sees the diagnostic anyway and would report the test as risky for it. Returning TRUE
        // says the diagnostic is handled, so nothing propagates.
        set_error_handler(function($errno, $errstr) {
            return true;
        });

        try {
            $claimed = InstallManager::claimInstallLock($readonly);
        }
        finally {
            restore_error_handler();
        }

        try {
            $this->assertFalse($claimed);
            $this->assertFalse(InstallManager::installLockExists($readonly), 'no claim exists, so this is a permissions problem, not a race');
        }
        finally {
            chmod($readonly, 0775);
            rmdir($readonly);
        }
    }

    public function testThePathIsBuiltInsideTheGivenDirectory(): void
    {
        $path = InstallManager::getInstallLockPath($this->tempDir);

        $this->assertSame($this->tempDir . '/install.lock', $path);
        $this->assertSame($path, InstallManager::getInstallLockPath($this->tempDir . '/'), 'a trailing slash must not double up');
    }
}
