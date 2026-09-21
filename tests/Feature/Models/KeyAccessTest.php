<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\ApiKey;
use App\Models\ApiAccessLevel;
use App\Models\ApiKeyAccessLog;
use App\Models\ApiIpKeyCount;
use App\Models\IpAccess;
use App\Models\IpAccessLog;
use Illuminate\Support\Facades\DB;

class KeyAccessTest extends TestCase 
{
    // testBasicKey asserts a BASIC key is *not* unlimited, which only holds while the
    // configured cap is non-zero - so it opts out of the suite-wide lift in TestCase.
    protected $lift_daily_access_limit = FALSE;

    private $key_cache = [];

    public function testInvalidKey() 
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = ApiKey::generateKeyHash();
        $this->assertNotContains($key, $this->key_cache, 'Duplicate Key!');
        $this->key_cache[] = $key;

        $Key = ApiKey::findByKey($key);

        $this->assertEmpty($Key);
    }

    public function testNoAccessKey() 
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = $this->_fakeKey(ApiAccessLevel::NONE);
        $this->assertNotContains($key, $this->key_cache, 'Duplicate Key!');
        $this->key_cache[] = $key;

        $Key = ApiKey::findByKey($key);

        $this->assertNotEmpty($Key);
        $this->assertFalse($Key->hasUnlimitedAccess());
        $this->assertFalse($Key->accessLevel->hasBasicAccess());
        $this->assertTrue($Key->accessLevel->hasNoAccess());
        $this->assertFalse($Key->accessLevel->hasActionAccess('statistics'));
        $this->assertFalse($Key->accessLevel->hasActionAccess('commentaries'));
        $this->assertFalse($Key->accessLevel->hasActionAccess('dictionaries'));
        $this->assertFalse($Key->accessLevel->hasActionAccess('query'));
        $this->assertFalse($Key->accessLevel->hasActionAccess('statics'));
        $this->assertFalse($Key->accessLevel->hasActionAccess('strongs'));
        $Key->delete();
    }

    public function testDeletedKey() 
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = $this->_fakeKey(ApiAccessLevel::BASIC);
        $this->assertNotContains($key, $this->key_cache, 'Duplicate Key!');
        $this->key_cache[] = $key;

        $Key = ApiKey::findByKey($key);
        $Key->delete();
        
        $this->assertEquals(ApiAccessLevel::NONE, $Key->access_level_id);
        
        // Deleted models won't load by default
        $Key = ApiKey::findByKey($key);
        $this->assertEmpty($Key);
    }

    public function testBasicKey() 
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = $this->_fakeKey(ApiAccessLevel::BASIC);
        $this->assertNotContains($key, $this->key_cache, 'Duplicate Key!');
        $this->key_cache[] = $key;

        $Key = ApiKey::findByKey($key);

        $this->assertNotEmpty($Key);
        
        if(config('bss.daily_access_limit') === 0) {
            $this->assertTrue($Key->hasUnlimitedAccess());
        } else {
            $this->assertFalse($Key->hasUnlimitedAccess());
        }

        $this->assertTrue($Key->accessLevel->hasBasicAccess());
        $this->assertFalse($Key->accessLevel->hasNoAccess());
        $this->assertFalse($Key->accessLevel->hasActionAccess('statistics'));
        $this->assertFalse($Key->accessLevel->hasActionAccess('commentaries'));
        $this->assertFalse($Key->accessLevel->hasActionAccess('dictionaries'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('query'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('statics'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('strongs'));
        $Key->delete();
    }    

    public function testFullAccesssKey() 
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = $this->_fakeKey(ApiAccessLevel::FULL);
        $this->assertNotContains($key, $this->key_cache, 'Duplicate Key!');
        $this->key_cache[] = $key;

        $Key = ApiKey::findByKey($key);

        $this->assertNotEmpty($Key);
        $this->assertTrue($Key->hasUnlimitedAccess());
        $this->assertTrue($Key->accessLevel->hasBasicAccess());
        $this->assertFalse($Key->accessLevel->hasNoAccess());
        $this->assertTrue($Key->accessLevel->hasActionAccess('statistics'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('commentaries'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('dictionaries'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('query'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('statics'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('strongs'));
        $this->assertTrue($Key->accessLevel->hasActionAccess('doesnotexist')); // returns true because the API controller checks whether an action exists
        $Key->delete();
    }

    public function testAccessLogs()
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        // Generate our key
        $key = $this->_fakeKey(ApiAccessLevel::BASIC);
        $this->assertNotContains($key, $this->key_cache, 'Duplicate Key!');
        $this->key_cache[] = $key;
        $Key = ApiKey::findByKey($key);
        $this->assertNotEmpty($Key);

        // Generate a fake IP
        $ip = $this->_fakeIp();
        $_SERVER['REMOTE_ADDR'] = $ip;
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);

        $this->assertTrue($Key->incrementDailyHits());

        $KeyAccessLog = ApiKeyAccessLog::where('key_id', $Key->id)->where('date', date('Y-m-d'))->first();
        $this->assertNotEmpty($KeyAccessLog);
        $this->assertEquals(1, $KeyAccessLog->count);

        $IpKeyCount = ApiIpKeyCount::where('key_id', $Key->id)->where('ip_id', $IP->id)->where('date', date('Y-m-d'))->first();

        $this->assertNotEmpty($IpKeyCount);
        $this->assertEquals(1, $IpKeyCount->count);

        $IpAccessLog = IpAccessLog::where('ip_id', $IP->id)->where('date', date('Y-m-d'))->first();
        $this->assertEmpty($IpAccessLog); // No IpAccessLog because we're not counting against the IP
    }

    /**
     * api_ip_key_count carries a unique index on (key_id, ip_id, date), and the tracking
     * update used firstOrNew -> count++ -> save(). Two concurrent requests for the same
     * key and IP could both find no row, both insert, and the loser raise -- a 500 handed
     * back for an access whose quota had already been spent.
     *
     * A row that already exists is the deterministic stand-in for the losing racer's
     * insert: the old code would have tried to create a second one.
     */
    public function testTrackingCountIsAtomicAndNeverDuplicates(): void
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = $this->_fakeKey(ApiAccessLevel::BASIC);
        $Key = ApiKey::findByKey($key);
        $date = date('Y-m-d');

        try {
            $this->assertTrue($Key->incrementDailyHits(), 'First access should be granted');

            $rows = ApiIpKeyCount::where('key_id', $Key->id)->where('date', $date)->get();
            $this->assertCount(1, $rows, 'One tracking row per key/ip/date');
            $this->assertSame(1, (int) $rows->first()->count);

            // The row now exists, which is the state a racing insert would collide with.
            $this->assertTrue($Key->incrementDailyHits(), 'Second access should not raise');

            $rows = ApiIpKeyCount::where('key_id', $Key->id)->where('date', $date)->get();
            $this->assertCount(1, $rows, 'Still exactly one tracking row');
            $this->assertSame(2, (int) $rows->first()->count, 'The count must accumulate');
        }
        finally {
            ApiIpKeyCount::where('key_id', $Key->id)->delete();
            ApiKeyAccessLog::where('key_id', $Key->id)->delete();
            $Key->forceDelete();
        }
    }

    /**
     * The race itself, made deterministic: two model instances are built while the row
     * does not exist yet -- exactly the state two concurrent requests are in -- and then
     * saved. The old firstOrNew -> count++ -> save() sequence makes the second one INSERT
     * into the unique index and raise; the atomic helper absorbs it.
     */
    public function testTheTrackingRaceIsAbsorbedRatherThanRaised(): void
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = $this->_fakeKey(ApiAccessLevel::BASIC);
        $Key = ApiKey::findByKey($key);
        $IP  = IpAccess::findOrCreateByIpOrDomain(true);
        $keys = ['key_id' => $Key->id, 'ip_id' => $IP->id, 'date' => date('Y-m-d')];

        $helper = new \ReflectionMethod(ApiKey::class, 'incrementTrackingCountAtomic');

        try {
            // Both "requests" look first and find nothing.
            $stale_a = ApiIpKeyCount::firstOrNew($keys);
            $stale_b = ApiIpKeyCount::firstOrNew($keys);

            $this->assertFalse($stale_a->exists, 'Precondition: no row yet');
            $this->assertFalse($stale_b->exists, 'Precondition: both racers saw no row');

            $stale_a->count++;
            $stale_a->save();

            // What the old code did next, and why it had to change.
            $stale_b->count++;

            try {
                $stale_b->save();
                $this->fail('The old sequence should collide with the unique index');
            }
            catch(\Illuminate\Database\UniqueConstraintViolationException $e) {
                // expected
            }

            // The atomic helper, given the same already-occupied row, simply counts.
            $helper->invoke($Key, ApiIpKeyCount::class, $keys);
            $helper->invoke($Key, ApiIpKeyCount::class, $keys);

            $rows = ApiIpKeyCount::where($keys)->get();

            $this->assertCount(1, $rows, 'Still one row');
            $this->assertSame(3, (int) $rows->first()->count, 'Every increment landed');
        }
        finally {
            ApiIpKeyCount::where('key_id', $Key->id)->delete();
            ApiKeyAccessLog::where('key_id', $Key->id)->delete();
            $Key->forceDelete();
        }
    }

    /**
     * Neither counter re-inserts its row on every request. INSERT IGNORE allocates an
     * AUTO_INCREMENT value before InnoDB detects the duplicate key, so one per request
     * advances api_key_access_log.id and api_ip_key_count.id per request rather than per
     * key per day -- and both columns are `int unsigned`.
     */
    public function testTheDailyRowsAreNotReInsertedOnEveryHit(): void
    {
        if(!config('app.experimental')) {
            $this->markTestSkipped('Experimental functionality, skipping tests.');
        }

        $key = $this->_fakeKey(ApiAccessLevel::BASIC);
        $Key = ApiKey::findByKey($key);

        try {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $Key->incrementDailyHits();
            $Key->incrementDailyHits();
            $Key->incrementDailyHits();

            $queries = array_column(DB::getQueryLog(), 'query');

            $inserts = array_values(array_filter($queries, function($query) {
                return stripos(ltrim($query), 'insert') === 0;
            }));

            // One for api_key_access_log, one for api_ip_key_count, both on the first hit.
            $this->assertCount(2, $inserts, 'the daily rows may only be inserted once: ' . implode(' | ', $inserts));

            $this->assertSame(3, (int) ApiKeyAccessLog::where('key_id', $Key->id)->where('date', date('Y-m-d'))->value('count'));
            $this->assertSame(3, (int) ApiIpKeyCount::where('key_id', $Key->id)->where('date', date('Y-m-d'))->value('count'));
        }
        finally {
            DB::disableQueryLog();
            DB::flushQueryLog();

            ApiIpKeyCount::where('key_id', $Key->id)->delete();
            ApiKeyAccessLog::where('key_id', $Key->id)->delete();
            $Key->forceDelete();
        }
    }

    protected function _fakeKey($access_level_id = null)
    {
        $key_hash = ApiKey::generateKeyHash();

        $Key = new ApiKey;
        $Key->key = $key_hash;
        $Key->access_level_id = $access_level_id ?: ApiAccessLevel::BASIC;

        // api_keys.user_id is NOT NULL with no default. MySQL in a non-strict mode fills it
        // with 0 silently - which is what every existing row holds - but SQLite rejects the
        // insert, so these tests only ever ran on MySQL. Set it explicitly to keep the same
        // stored value on both.
        $Key->user_id = 0;

        $Key->save();

        return $key_hash;
    }

    protected function _fakeIp() 
    {
        // Ip addresses intentionally invalid
        return rand(256,999) . '.' . rand(1,255) . '.' . rand(1,255) . '.' . rand(1,255);
    }
}
