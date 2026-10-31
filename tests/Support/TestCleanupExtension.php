<?php

namespace Tests\Support;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Sweeps what earlier test runs left behind (`php artisan test:cleanup`) once, before any test
 * starts.  Each test removes its own fixtures in tearDown; this covers the runs that died
 * before tearDown could - a fatal error, a timeout, Ctrl-C.
 *
 * It runs while the extension is bootstrapped, in the process that starts the run.  paratest
 * also bootstraps extensions in each worker, and those carry a TEST_TOKEN: they skip the
 * sweep, as other workers may already be running tests by then.
 *
 * The sweep runs as a separate process: booting the application in here would leave Laravel's
 * error handler installed, and PHPUnit would then flag every test as risky.  The child inherits
 * this process's environment, phpunit.xml's <env> values included.
 *
 * Another test run may be going on alongside, such as a focused run started while the full
 * suite runs, and some fixtures (the throwaway languages) are swept whatever their age.  So each
 * run holds a shared lock on a lock file until its process exits, and the sweep only runs when
 * this run can take that lock exclusively: when no other run on this checkout holds it.  The
 * lock covers runs on this machine only, not other machines sharing the database.
 *
 * Set TEST_CLEANUP=false to turn it off.  A failing sweep never stops the run.
 */
final class TestCleanupExtension implements Extension
{
    /**
     * The lock file, held open (and locked) until the process exits
     *
     * @var resource|null
     */
    private static $lock = NULL;

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if(getenv('TEST_TOKEN') !== FALSE || filter_var(getenv('TEST_CLEANUP') ?: 'true', FILTER_VALIDATE_BOOLEAN) === FALSE) {
            return;
        }

        if(!self::lockRun()) {
            $reason = self::$lock ? 'another test run is in progress' : 'the run lock file could not be opened';
            fwrite(STDERR, 'test:cleanup sweep skipped: ' . $reason . PHP_EOL);
            return;
        }

        try {
            self::sweep();
        }
        finally {
            // From here on this run only needs to keep later runs from sweeping
            if(self::$lock) {
                flock(self::$lock, LOCK_SH);
            }
        }
    }

    /**
     * Opens the lock file and takes the run lock.  TRUE when this run may sweep, FALSE when
     * another run holds the lock or the lock file cannot be opened: without the lock there is
     * no telling whether another run is going on.
     *
     * @return bool
     */
    private static function lockRun(): bool
    {
        $lock = self::openLock(sys_get_temp_dir() . '/bss-test-run-' . md5(dirname(__DIR__, 2)) . '.lock');

        if(!$lock) {
            return FALSE;
        }

        self::$lock = $lock;
        return self::takeLock($lock);
    }

    /**
     * Opens the lock file, creating it if need be.  Read-only when it can't be opened for writing,
     * such as another user's file in a sticky /tmp (fs.protected_regular refuses that even when
     * its permissions allow it): flock() works on a read-only handle too.
     *
     * A failed open's warning is silenced with a handler rather than @, which Collision still
     * reports.
     *
     * @return resource|null
     */
    public static function openLock(string $path)
    {
        set_error_handler(fn(): bool => TRUE);

        try {
            return (fopen($path, 'c') ?: fopen($path, 'r')) ?: NULL;
        }
        finally {
            restore_error_handler();
        }
    }

    /**
     * Takes a lock on an open lock file: exclusively if no one else holds it (TRUE), otherwise
     * shared (FALSE), waiting for a sweep in progress elsewhere to finish.
     *
     * @param resource $lock
     * @return bool
     */
    public static function takeLock($lock): bool
    {
        if(flock($lock, LOCK_EX | LOCK_NB)) {
            return TRUE;
        }

        flock($lock, LOCK_SH);
        return FALSE;
    }

    private static function sweep(): void
    {

        $artisan = dirname(__DIR__, 2) . '/artisan';
        $process = proc_open([PHP_BINARY, $artisan, 'test:cleanup', '--no-ansi'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if(!is_resource($process)) {
            fwrite(STDERR, 'test:cleanup sweep skipped: could not start it' . PHP_EOL);
            return;
        }

        $output = trim(stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        if($exit !== 0) {
            fwrite(STDERR, 'test:cleanup sweep failed (exit ' . $exit . '): ' . $output . PHP_EOL);
        }
        elseif($output !== '' && !str_starts_with($output, 'No test leftovers')) {
            fwrite(STDERR, $output . PHP_EOL);
        }
    }
}
