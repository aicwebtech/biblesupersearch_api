<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests\TestCase::deferCleanup() / runDeferredCleanups(): the stack every feature test's
 * fixture cleanup goes through.  Exercised on an instance that is never run, so no application
 * is booted.
 *
 * No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the method is
 * deprecated in 8.5, which CI runs.
 */
class DeferredCleanupTest extends TestCase
{
    private function makeCase(): \Tests\TestCase
    {
        return new class('cleanupStackFixture') extends \Tests\TestCase {
            public function cleanupStackFixture(): void
            {
            }
        };
    }

    private function defer(\Tests\TestCase $Case, callable $cleanup): void
    {
        (new \ReflectionMethod(\Tests\TestCase::class, 'deferCleanup'))->invoke($Case, $cleanup);
    }

    private function runCleanups(\Tests\TestCase $Case): void
    {
        (new \ReflectionMethod(\Tests\TestCase::class, 'runDeferredCleanups'))->invoke($Case);
    }

    public function testCleanupsRunLastInFirstOut(): void
    {
        $Case = $this->makeCase();
        $ran  = [];

        $this->defer($Case, function() use (&$ran) { $ran[] = 'first'; });
        $this->defer($Case, function() use (&$ran) { $ran[] = 'second'; });
        $this->runCleanups($Case);

        $this->assertSame(['second', 'first'], $ran);
    }

    public function testEveryCleanupRunsWhenOneThrowsAndTheFirstErrorIsRethrown(): void
    {
        $Case = $this->makeCase();
        $ran  = [];

        $this->defer($Case, function() use (&$ran) { $ran[] = 'oldest'; });
        $this->defer($Case, function() { throw new \RuntimeException('older failure'); });
        $this->defer($Case, function() { throw new \LogicException('newest failure'); });

        try {
            $this->runCleanups($Case);
            $this->fail('The cleanup error was swallowed');
        }
        catch(\LogicException $e) {
            $this->assertSame('newest failure', $e->getMessage());
        }

        $this->assertSame(['oldest'], $ran, 'A throwing cleanup stopped the ones after it');
    }

    public function testCleanupsRunOnlyOnce(): void
    {
        $Case  = $this->makeCase();
        $count = 0;

        $this->defer($Case, function() use (&$count) { $count++; });
        $this->runCleanups($Case);
        $this->runCleanups($Case);

        $this->assertSame(1, $count);
    }
}
