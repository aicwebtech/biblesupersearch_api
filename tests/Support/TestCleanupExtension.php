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
 * Set TEST_CLEANUP=false to turn it off.  A failing sweep never stops the run.
 */
final class TestCleanupExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        if(getenv('TEST_TOKEN') !== FALSE || filter_var(getenv('TEST_CLEANUP') ?: 'true', FILTER_VALIDATE_BOOLEAN) === FALSE) {
            return;
        }

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
