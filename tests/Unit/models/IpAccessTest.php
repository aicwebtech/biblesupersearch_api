<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
//use Faker\Generator;
use App\Models\IpAccess;

class IpAccessTest extends TestCase {
    protected $default_limit;

//    public function __construct($name = null, array $data = array(), $dataName = '') {
//        parent::__construct($name, $data, $dataName);
//        $default_limit = env('DAILY_ACCESS_LIMIT', 1000);
//    }

    public function testDefaultLimit() {
        $default_limit = config('bss.daily_access_limit');
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);

        $this->assertEquals($default_limit, $IP->getAccessLimit());
        $this->assertEquals(0, $IP->getDailyHits());

        $IP->incrementDailyHits();
        $this->assertEquals(1, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        for($hits = 2; $hits < $default_limit; $hits ++) {
            $IP->incrementDailyHits();
        }

        $this->assertFalse($IP->isLimitReached());
        $IP->incrementDailyHits();
        $this->assertTrue($IP->isLimitReached());
        $IP->delete();
    }

    public function testCustomLimit() {
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
        $IP->incrementDailyHits();
        $this->assertTrue($IP->isLimitReached());
        $IP->delete();
    }

    public function testDomainCustomLimit() {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, 'example.com');
        $limit = 125;
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

    protected function _fakeIp() {
        // Ip addresses intentionally invalid
        return rand(256,999) . '.' . rand(1,255) . '.' . rand(1,255) . '.' . rand(1,255);
    }
}
