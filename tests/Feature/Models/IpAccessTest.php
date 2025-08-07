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
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, 'testdomaincustomlimit.com');
        $limit = 25;
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
        $IP->incrementDailyHits();
        $this->assertTrue($IP->isLimitReached());
        $IP->delete();
    }


    /*
        * Test that the domain is parsed correctly and the limit is set
        * based on the domain
        * @depends testDomainCustomLimit
        */
    public function testSameDomain() 
    {
        $domain = 'http://www.testsamedomain.com';

        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.example.com';

        $IP = IpAccess::findOrCreateByIpOrDomain($this->_fakeIp(), $domain);
        $this->assertEquals($IP->getAccessLimit(false), config('bss.daily_access_limit'));

        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.testsamedomain.com';
        $this->assertEquals($IP->getAccessLimit(false), 0, $IP->domain . ' should = ' . $domain);

        $IP->delete();
    }

    protected function _fakeIp() 
    {
        // Ip addresses intentionally invalid
        return rand(256,999) . '.' . rand(1,255) . '.' . rand(1,255) . '.' . rand(1,255);
    }
}
