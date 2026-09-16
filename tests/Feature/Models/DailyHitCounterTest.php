<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\IpAccess;
use App\Models\IpAccessLog;
use Illuminate\Support\Facades\DB;

/**
 * incrementDailyHits() used to be read-then-write (firstOrNew -> count++ ->
 * save), so concurrent requests overwrote each other's increments and a client
 * could exceed the daily limit. Accounting is now done by the database.
 *
 * A genuine race is not reproducible in a single-process test run, so these
 * cover the observable contract instead: the limit is enforced exactly, no
 * duplicate row is created for the day, and the conditional update - not the
 * cached limit_reached flag - is what refuses the request.
 */
class DailyHitCounterTest extends TestCase
{
    protected $lift_daily_access_limit = FALSE;

    /**
     * Purpose-built access row; removed by the caller in a finally.
     */
    protected function makeIpFixture(int $limit): IpAccess
    {
        $Access = new IpAccess();
        $Access->ip_address = '203.0.113.' . random_int(2, 250); // TEST-NET-3
        $Access->domain = null;
        $Access->limit = $limit;
        $Access->save();

        return $Access;
    }

    protected function removeIpFixture(?IpAccess $Access): void
    {
        if($Access) {
            IpAccessLog::where('ip_id', $Access->id)->delete();
            $Access->delete();
        }
    }

    public function testLimitIsEnforcedExactly(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(3);

            $this->assertTrue($Access->incrementDailyHits(), 'hit 1 should be allowed');
            $this->assertTrue($Access->incrementDailyHits(), 'hit 2 should be allowed');
            $this->assertTrue($Access->incrementDailyHits(), 'hit 3 should be allowed');
            $this->assertFalse($Access->incrementDailyHits(), 'hit 4 must be refused');
            $this->assertFalse($Access->incrementDailyHits(), 'hit 5 must be refused');

            $this->assertSame(3, $Access->getDailyHits(), 'count must stop at the limit');
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    public function testOnlyOneLogRowIsCreatedPerDay(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(5);

            $Access->incrementDailyHits();
            $Access->incrementDailyHits();

            $rows = IpAccessLog::where('ip_id', $Access->id)
                               ->where('date', date('Y-m-d'))
                               ->count();

            $this->assertSame(1, $rows, 'insertOrIgnore must not create a second row for the day');
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    public function testLimitReachedFlagIsSet(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(2);

            $Access->incrementDailyHits();
            $this->assertFalse($Access->isLimitReached(), 'not reached after 1 of 2');

            $Access->incrementDailyHits();
            $this->assertTrue($Access->isLimitReached(), 'reached after 2 of 2');
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    /**
     * The conditional UPDATE is the real gate. Even with the cached flag
     * cleared - the state a lost update could leave behind - a request beyond
     * the limit is still refused.
     */
    public function testRefusalDoesNotDependOnCachedFlag(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(2);

            $Access->incrementDailyHits();
            $Access->incrementDailyHits();

            IpAccessLog::where('ip_id', $Access->id)
                       ->where('date', date('Y-m-d'))
                       ->update(['limit_reached' => 0]);

            $this->assertFalse($Access->incrementDailyHits(), 'count guard must still refuse');
            $this->assertSame(2, $Access->getDailyHits());
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    /**
     * The cached flag must track the limit in both directions. An operator who
     * raises bss.daily_access_limit (or a key's access level) part way through
     * the day starts getting requests served again, so isLimitReached() -- which
     * Engine::actionStatics() reports to the client -- must stop claiming a
     * block that is no longer enforced.
     */
    public function testLimitReachedFlagIsClearedWhenTheLimitIsRaised(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(2);

            $Access->incrementDailyHits();
            $Access->incrementDailyHits();

            $this->assertTrue($Access->isLimitReached(), 'reached after 2 of 2');
            $this->assertFalse($Access->incrementDailyHits(), 'a third hit is refused at the old limit');

            $Access->limit = 10;
            $Access->save();

            $this->assertTrue($Access->incrementDailyHits(), 'the raised limit must serve the request');
            $this->assertFalse($Access->isLimitReached(), 'the stale flag must be cleared, not just ignored');
            $this->assertSame(3, $Access->getDailyHits());
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    /**
     * The mirror case: the flag must still be set on the request that exhausts
     * the quota, not only cleared.
     */
    public function testLimitReachedFlagIsSetOnTheExhaustingRequest(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(3);

            $Access->incrementDailyHits();
            $Access->incrementDailyHits();
            $this->assertFalse($Access->isLimitReached(), 'not reached after 2 of 3');

            $Access->incrementDailyHits();
            $this->assertTrue($Access->isLimitReached(), 'reached after 3 of 3');
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    /**
     * A limit of 0 means unlimited and must keep incrementing.
     */
    public function testUnlimitedAccessKeepsCounting(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(0);

            $this->assertTrue($Access->incrementDailyHits());
            $this->assertTrue($Access->incrementDailyHits());
            $this->assertTrue($Access->incrementDailyHits());

            $this->assertSame(3, $Access->getDailyHits());
            $this->assertFalse($Access->isLimitReached());
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }
}
