<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\IpAccess;

/* Use case: public API access is ENABLED */
/* See also class IpAccessPrivateTest */
class IpAccessTest extends TestCase 
{
    // testDefaultLimit / testSameDomain assert on the configured cap being enforced, which the
    // suite-wide lift in TestCase would turn into a no-limit case.
    protected $lift_daily_access_limit = FALSE;

    protected $default_limit;
    protected $config_cache;
    protected $config_value = 1;
    protected $config_changed = false;

    public function setUp() :void
    {
        parent::setUp();

        $this->config_cache = config('bss.public_access');
        $this->config_changed = false;

        if($this->config_cache != $this->config_value) {
            config(['bss.public_access' => $this->config_value]);
            $this->config_changed = true;
        }
    }

    public function tearDown() :void
    {
        if($this->config_changed) {
            config(['bss.public_access' => $this->config_cache]);
        }
    }

    public function testDefaultLimit() 
    {
        $default_limit = config('bss.daily_access_limit');

        $no_limit = ($default_limit == 0);

        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);

        $this->assertEquals($default_limit, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());

        $IP->incrementDailyHits();
        $this->assertEquals(1, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        if($no_limit) {
            $this->assertEquals(0, $IP->getAccessLimit());
            $this->assertTrue($IP->hasUnlimitedAccess());
        } else {
            // Speed up this test by setting the current count to limit - 5
            $Log = $IP->getAccessLog();
            $Log->count = $default_limit - 5;
            $Log->save();

            $this->assertEquals($default_limit - 5, $IP->getDailyHits());

            for($i = 1; $i < 5; $i ++) {
                $IP->incrementDailyHits();
            }

            $this->assertFalse($IP->isLimitReached());
            // Next hit will push it over the limit
            $this->assertTrue( $IP->incrementDailyHits() );
            $this->assertTrue($IP->isLimitReached());
            $this->assertFalse( $IP->incrementDailyHits() );
        }

        $IP->delete();
    }

    public function testNoLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);
        $limit = 0;
        $IP->limit = $limit;
        $IP->save();

        $this->assertEquals($limit, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());
        $this->assertTrue($IP->hasUnlimitedAccess());

        $IP->incrementDailyHits();
        $this->assertEquals(1, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        $this->assertFalse($IP->isLimitReached());
        $IP->incrementDailyHits();
        $IP->delete();
    }    

    public function testCustomLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);
        $limit = 20;
        $IP->limit = $limit;
        $IP->save();

        $this->assertEquals($limit, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());

        $IP->incrementDailyHits();
        $this->assertEquals(1, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        for($hits = 2; $hits < $limit; $hits ++) {
            $IP->incrementDailyHits();
        }

        $this->assertFalse($IP->isLimitReached());
        // Next hit will push it over the limit
        $this->assertTrue( $IP->incrementDailyHits() );
        $this->assertTrue($IP->isLimitReached());
        $this->assertFalse( $IP->incrementDailyHits() );
        $IP->delete();
    }

    public function testDomainCustomLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, $this->fixtureDomain('testdomaincustomlimit'));
        $limit = 25;
        $IP->limit = $limit;
        $IP->save();

        try {
            $this->assertEquals($limit, $IP->getAccessLimit());
            $this->assertEquals(0, $IP->getDailyHits());

            $IP->incrementDailyHits();
            $this->assertEquals(1, $IP->getDailyHits());
            $this->assertFalse($IP->isLimitReached());

            for($hits = 2; $hits < $limit; $hits ++) {
                $IP->incrementDailyHits();
            }

            $this->assertFalse($IP->isLimitReached());
            $IP->incrementDailyHits();
            $this->assertTrue($IP->isLimitReached());
        }
        finally {
            // A bucket left behind by a failed assertion poisons every later run of this test.
            $IP->delete();
        }
    }


    /*
        * Test that the domain is parsed correctly and the limit is set
        * based on the domain
        * @depends testDomainCustomLimit
        */
    public function testSameDomain() 
    {
        $host   = $this->fixtureDomain('testsamedomain');
        $domain = 'http://www.' . $host;

        // ApiAccessManager::trustedDomain() reads these, so the host this test fakes would
        // otherwise become the API's own host for every later test in the same worker process.
        $server_snapshot = [
            'HTTP_HOST'   => $_SERVER['HTTP_HOST'] ?? NULL,
            'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? NULL,
        ];

        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.example.com';

        $IP = IpAccess::findOrCreateByIpOrDomain($this->_fakeIp(), $domain);

        try {
            $this->assertEquals($IP->getAccessLimit(false), config('bss.daily_access_limit'));

            $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.' . $host;
            $this->assertEquals($IP->getAccessLimit(false), 0, $IP->domain . ' should = ' . $domain);
        }
        finally {
            $this->restoreRequestHost($server_snapshot);
            $IP->delete();
        }

        $this->assertSame(
            $server_snapshot,
            ['HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? NULL, 'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? NULL],
            'the faked host must not outlive the test'
        );
    }

    protected function _fakeIp() 
    {
        // Ip addresses intentionally invalid
        return rand(256,999) . '.' . rand(1,255) . '.' . rand(1,255) . '.' . rand(1,255);
    }
}
