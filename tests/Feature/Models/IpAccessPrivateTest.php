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
    // testDefaultLimit only differs from testNoLimit while the configured cap is non-zero, so
    // it opts out of the suite-wide lift in TestCase.
    protected $lift_daily_access_limit = FALSE;

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
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, $this->fixtureDomain('testdomaincustomlimit'));
        $limit = 125;
        $IP->limit = $limit;
        $IP->save();

        $this->helper($IP, $limit);
    }

    protected function helper($IP, $limit) 
    {
        try {
            $this->assertNotEquals($limit, $IP->getAccessLimit());
            $this->assertLessThan(0, $IP->getAccessLimit());
            $this->assertEquals(0, $IP->getDailyHits());

            $this->assertFalse( $IP->incrementDailyHits() );
            $this->assertEquals(0, $IP->getDailyHits());
            $this->assertFalse($IP->isLimitReached());

            $this->assertTrue($IP->isAccessRevoked());
        }
        finally {
            // A bucket left behind by a failed assertion poisons every later run of this test.
            $IP->delete();
        }
    }

    public function testSameDomain() 
    {
        // ApiAccessManager::trustedDomain() reads these, so the host this test fakes would
        // otherwise become the API's own host for every later test in the same worker process.
        $server_snapshot = [
            'HTTP_HOST'   => $_SERVER['HTTP_HOST'] ?? NULL,
            'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? NULL,
        ];

        $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.example.com';

        $host   = $this->fixtureDomain('testsamedomain');
        $domain = 'http://www.' . $host;

        $IP = IpAccess::findOrCreateByIpOrDomain($this->_fakeIp(), $domain);

        try {
            $this->assertTrue($IP->isAccessRevoked());

            $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'www.' . $host;
            $this->assertEquals($IP->getAccessLimit(), 0);
            $this->assertTrue($IP->hasUnlimitedAccess());
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
