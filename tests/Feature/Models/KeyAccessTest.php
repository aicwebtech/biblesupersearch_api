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

class KeyAccessTest extends TestCase 
{

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

    protected function _fakeKey($access_level_id = null)
    {
        $key_hash = ApiKey::generateKeyHash();

        $Key = new ApiKey;
        $Key->key = $key_hash;
        $Key->access_level_id = $access_level_id ?: ApiAccessLevel::BASIC;
        $Key->save();

        return $key_hash;
    }

    protected function _fakeIp() 
    {
        // Ip addresses intentionally invalid
        return rand(256,999) . '.' . rand(1,255) . '.' . rand(1,255) . '.' . rand(1,255);
    }
}
