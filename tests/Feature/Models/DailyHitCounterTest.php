<?php

namespace Tests\Feature\Models;

use Tests\TestCase;
use App\Models\ApiIpKeyCount;
use App\Models\IpAccess;
use App\Models\IpAccessLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * The other direction: lowering the limit below a count the day has already passed.
     *
     * The increment is conditional on count < limit, so it stops matching the moment the
     * limit drops under the count -- and the marker, which is only maintained after an
     * increment lands, was never set. isLimitReached() went on reporting FALSE while
     * every request was refused, so /statics showed access.limit_reached = false beside
     * the new, lower access.limit and a client had no way to reconcile the two.
     */
    public function testLimitReachedFlagIsSetWhenTheLimitIsLowered(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(10);

            $Access->incrementDailyHits();
            $Access->incrementDailyHits();

            $this->assertFalse($Access->isLimitReached(), 'not reached at 2 of 10');

            $Access->limit = 2;
            $Access->save();

            $this->assertFalse($Access->incrementDailyHits(), 'the lowered limit must refuse the request');
            $this->assertTrue($Access->isLimitReached(), 'and the marker must agree with the refusal');
            $this->assertSame(2, $Access->getDailyHits(), 'a refused hit is not counted');

            // Corrective, not unconditional: with the marker already right, a further
            // refusal must leave the row alone rather than rewrite it on every blocked
            // request. An untouched updated_at is what says no row was written.
            $Log = IpAccessLog::where('ip_id', $Access->id)->firstOrFail();
            $sentinel = '2000-01-01 00:00:00';

            IpAccessLog::where('id', $Log->id)->update(['updated_at' => $sentinel]);

            $this->assertFalse($Access->incrementDailyHits());

            $this->assertSame(
                $sentinel,
                IpAccessLog::where('id', $Log->id)->firstOrFail()->updated_at->toDateTimeString(),
                'a refusal against a correct marker must not write'
            );

            $this->assertTrue($Access->isLimitReached(), 'and the marker stays set');
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
     * The row is created once a day, not attempted once a request.
     *
     * INSERT IGNORE is cheap only in the sense that it does not raise: InnoDB allocates an
     * AUTO_INCREMENT value before it notices the duplicate key, so issuing one per request
     * advances ip_access_log.id per request rather than per IP per day. That column is
     * `int unsigned`, and exhausting it would leave every later insert failing on a table
     * that only ever appends.
     */
    public function testTheLogRowIsNotReInsertedOnEveryHit(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(10);

            $inserts = $this->insertsIssuedDuring(function() use ($Access) {
                $Access->incrementDailyHits();
                $Access->incrementDailyHits();
                $Access->incrementDailyHits();
            });

            $this->assertCount(1, $inserts, 'only the first hit of the day may insert: ' . implode(' | ', $inserts));
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    /**
     * And nothing at all is inserted once the quota is spent -- the refused client is the
     * one issuing the most requests, so it must not be the one burning the id range
     * fastest.
     */
    public function testARefusedHitInsertsNothing(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(1);

            $this->assertTrue($Access->incrementDailyHits());

            $inserts = $this->insertsIssuedDuring(function() use ($Access) {
                $this->assertFalse($Access->incrementDailyHits());
                $this->assertFalse($Access->incrementDailyHits());
            });

            $this->assertSame([], $inserts, 'a refused hit must not insert');
        }
        finally {
            $this->removeIpFixture($Access);
        }
    }

    /**
     * The INSERT statements the given callback caused the connection to run.
     *
     * @param  callable  $callback
     * @return array<int, string>
     */
    protected function insertsIssuedDuring(callable $callback): array
    {
        return $this->queriesIssuedDuring($callback, 'insert');
    }

    /**
     * The UPDATE statements the given callback caused the connection to run.
     *
     * @param  callable  $callback
     * @return array<int, string>
     */
    protected function updatesIssuedDuring(callable $callback): array
    {
        return $this->queriesIssuedDuring($callback, 'update');
    }

    /**
     * @param  callable  $callback
     * @param  string    $verb  the statement the caller is interested in
     * @return array<int, string>
     */
    protected function queriesIssuedDuring(callable $callback, string $verb): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            $queries = array_column(DB::getQueryLog(), 'query');
        }
        finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        return array_values(array_filter($queries, function($query) use ($verb) {
            return stripos(ltrim($query), $verb) === 0;
        }));
    }

    /**
     * A row that could not be created used to be indistinguishable from a quota that had been
     * spent: the conditional UPDATE matches nothing either way. The client got a 429 on every
     * request with no row and no log entry accounting for it, and the corrective marker write
     * fired against a row that was not there.
     *
     * insertOrIgnore is what makes the two look alike - INSERT IGNORE downgrades every insert
     * error to a warning, not only the duplicate key it is used for - so the failure is
     * arranged here at the same seam.
     */
    public function testAQuotaRowThatCannotBeCreatedIsReportedRatherThanCountedAsSpent(): void
    {
        $keys    = ['ip_id' => 0, 'date' => '1999-12-31'];
        $Counter = new DailyHitCounterWhoseRowCannotBeCreated();

        Log::spy();

        $updates = $this->updatesIssuedDuring(function() use ($Counter, $keys) {
            $this->assertFalse($Counter->increment(IpAccessLog::class, $keys, 5), 'a hit that cannot be accounted for must not be served');
        });

        Log::shouldHaveReceived('error')->once();

        $this->assertSame([], $updates, 'there is no row to mark, so nothing may be written: ' . implode(' | ', $updates));
        $this->assertSame(0, IpAccessLog::where($keys)->count(), 'and none may be left behind');
    }

    /**
     * The tracking tables carry no quota, so nothing is refused - but the increment would count
     * nothing at all, which is worth saying out loud rather than leaving to be noticed in the
     * numbers weeks later.
     */
    public function testATrackingRowThatCannotBeCreatedIsReported(): void
    {
        $keys    = ['key_id' => 0, 'ip_id' => 0, 'date' => '1999-12-31'];
        $Counter = new DailyHitCounterWhoseRowCannotBeCreated();

        Log::spy();

        $updates = $this->updatesIssuedDuring(function() use ($Counter, $keys) {
            $Counter->track(ApiIpKeyCount::class, $keys);
        });

        Log::shouldHaveReceived('error')->once();

        $this->assertSame([], $updates, 'there is no row to increment: ' . implode(' | ', $updates));
        $this->assertSame(0, ApiIpKeyCount::where($keys)->count());
    }

    /**
     * What the callers above turn on: the row is asked for again after the insert rather than
     * assumed, and an insert that was quietly ignored for any reason other than the duplicate
     * key it is there for is reported as a failure.
     */
    public function testRowCreationReportsWhetherTheRowIsThere(): void
    {
        $Access = null;

        try {
            $Access = $this->makeIpFixture(5);

            $Log      = new IpAccessLog();
            $now      = $Log->freshTimestampString();
            $keys     = ['ip_id' => $Access->id, 'date' => date('Y-m-d')];
            $defaults = ['count' => 0, 'limit_reached' => 0, 'created_at' => $now, 'updated_at' => $now];

            // No setAccessible() call: reflection has ignored visibility since PHP 8.1 and the
            // method is deprecated in 8.5, which CI runs.
            $method = new \ReflectionMethod(IpAccess::class, 'createDailyRowIfMissing');

            $this->assertTrue(
                $method->invoke($Access, IpAccessLog::class, $Log->getTable(), $keys, $defaults),
                'the row it has just created has to be reported as there'
            );

            $this->assertTrue(
                $method->invoke($Access, IpAccessLog::class, $Log->getTable(), $keys, $defaults),
                'and so does one that was already there'
            );

            $this->assertSame(1, IpAccessLog::where($keys)->count(), 'the second call must not insert again');
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

/**
 * The trait with its one write that INSERT IGNORE can swallow taken away, which is not
 * otherwise reproducible: the conditions that make it fail - an exhausted AUTO_INCREMENT id, a
 * constraint violation - cannot be arranged against a live table.
 */
class DailyHitCounterWhoseRowCannotBeCreated
{
    use \App\Traits\DailyHitCounter;

    /**
     * @param  string $log_class
     * @param  array  $keys
     * @param  int    $limit
     * @return bool
     */
    public function increment($log_class, array $keys, $limit)
    {
        return $this->incrementDailyHitsAtomic($log_class, $keys, $limit);
    }

    /**
     * @param  string $model_class
     * @param  array  $keys
     * @return void
     */
    public function track($model_class, array $keys)
    {
        $this->incrementTrackingCountAtomic($model_class, $keys);
    }

    protected function createDailyRowIfMissing($model_class, $table, array $keys, array $defaults)
    {
        return FALSE;
    }
}
