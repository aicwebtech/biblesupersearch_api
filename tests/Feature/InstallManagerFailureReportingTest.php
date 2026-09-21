<?php

namespace Tests\Feature;

use App\InstallManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * install() answers with an INSTALL_* code, and it has to keep doing so when a step throws.
 *
 * Almost everything it runs can: the migration, the Bible and feature writes, the book list
 * build - which raises a RuntimeException on a short write - and the transaction that creates
 * the administrator. App\Http\Controllers\Admin\InstallController::install() calls this with no
 * guard of its own, so anything that passes this method reaches the operator as a raw framework
 * 500 instead of the install.error page, on an unauthenticated page that has no other way to
 * say what went wrong.
 *
 * The throw is arranged at existingUserCount(), which is the first thing inside the try that a
 * subclass can reach without running key:generate or migrate against the database these tests
 * are pointed at.
 */
class InstallManagerFailureReportingTest extends TestCase
{
    /**
     * Runs install() against a double that throws, with the installed flag cleared for the
     * duration.
     *
     * tests/TestCase::setUp() skips the whole suite unless the app is installed, so the
     * not-installed state can only be entered afterwards -- and has to be left again in a
     * finally, or later tests in this process inherit it.
     *
     * @return string the INSTALL_* code install() answered with
     */
    protected function runFailingInstall(): string
    {
        config(['app.installed' => false]);

        try {
            return InstallManagerThatThrows::install(new Request());
        }
        finally {
            config(['app.installed' => true]);
        }
    }

    /**
     * Artisan is mocked to refuse every call: the throw is arranged above key:generate, so a
     * call reaching it would mean the double stopped working rather than that the catch failed.
     */
    public function testAThrownExceptionIsReportedAsAFailedInstallRatherThanEscaping(): void
    {
        Artisan::shouldReceive('call')->never();
        Log::spy();

        $result = $this->runFailingInstall();

        $this->assertSame(InstallManager::INSTALL_FAILED, $result, 'a throw has to come back as a code the error page can render');

        Log::shouldHaveReceived('error')->once();
    }

    /**
     * The claim is what stops two installs running at once, and a failed attempt has to be
     * retryable: left on disk, it would answer every retry with INSTALL_IN_PROGRESS for an
     * install that is not running.
     */
    public function testTheInstallClaimIsReleasedWhenAStepThrows(): void
    {
        Artisan::shouldReceive('call')->never();
        Log::spy();

        $this->runFailingInstall();

        $this->assertFalse(InstallManager::installLockExists(), 'a failed attempt must leave no claim behind');
    }

    /**
     * error_reporting() is lowered for the duration of the install to swallow a deprecation
     * warning. That is a process wide setting, so a throw must not leave it lowered for
     * everything that runs afterwards.
     */
    public function testErrorReportingIsRestoredWhenAStepThrows(): void
    {
        Artisan::shouldReceive('call')->never();
        Log::spy();

        $before = error_reporting();

        $this->runFailingInstall();

        $this->assertSame($before, error_reporting(), 'the install must not lower error reporting for the rest of the process');
    }

    /**
     * The installer is unauthenticated, and the messages these exceptions carry are connection
     * strings, credentials and filesystem paths. The operator gets the code; the detail goes to
     * the log.
     */
    public function testTheExceptionDetailIsNotHandedBackToTheCaller(): void
    {
        Artisan::shouldReceive('call')->never();
        Log::spy();

        $result = $this->runFailingInstall();

        $this->assertStringNotContainsString(InstallManagerThatThrows::MESSAGE, $result);
    }
}

/**
 * A step that throws, arranged as high in install() as a subclass can reach.
 *
 * isInstalledInDatabase() is answered FALSE through late static binding rather than by clearing
 * the flag in the database, which would leave this installation looking uninstalled if the
 * process died mid-test.
 */
class InstallManagerThatThrows extends InstallManager
{
    const MESSAGE = 'SQLSTATE[HY000] [1045] Access denied for user';

    static function isInstalledInDatabase(): bool
    {
        return FALSE;
    }

    static protected function existingUserCount(): int
    {
        throw new \RuntimeException(static::MESSAGE);
    }
}
