<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\IpAccess;
use PHPUnit\Framework\Attributes\DataProvider;


/**
 * Use case: public API access is DISABLED 
 * See also class IpAccessTest
 * 
 * @depends IpAccessTest
 */
class IpAccessPrivateTest extends TestCase 
{
    protected $default_limit;
    protected $config_cache;
    protected $config_value = 0;
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

        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);

        $this->helper($IP, $default_limit);
    }

    public function testNoLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);
        $limit = 0;
        $IP->limit = $limit;
        $IP->save();

        $this->helper($IP, $limit);
    }    

    public function testCustomLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);
        $limit = 20;
        $IP->limit = $limit;
        $IP->save();

        $this->helper($IP, $limit);
    }

    public function testDomainCustomLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, 'testdomaincustomlimit.com');
        $limit = 125;
        $IP->limit = $limit;
        $IP->save();

        $this->helper($IP, $limit);
    }

    protected function helper($IP, $limit) 
    {
        $this->assertNotEquals($limit, $IP->getAccessLimit());
        $this->assertLessThan(0, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());

        $this->assertFalse( $IP->incrementDailyHits() );
        $this->assertEquals(0, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        $this->assertTrue($IP->isAccessRevoked());
        $IP->delete();
    }

    public function testSameDomain() 
    {
        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.example.com';
        
        $domain = 'http://www.testsamedomain.com';

        $IP = IpAccess::findOrCreateByIpOrDomain($this->_fakeIp(), $domain);
        $this->assertTrue($IP->isAccessRevoked());

        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.testsamedomain.com';
        $this->assertEquals($IP->getAccessLimit(), 0);
        $this->assertTrue($IP->hasUnlimitedAccess());

        $IP->delete();
    }

    protected function _fakeIp() 
    {
        // Ip addresses intentionally invalid
        return rand(256,999) . '.' . rand(1,255) . '.' . rand(1,255) . '.' . rand(1,255);
    }
}
