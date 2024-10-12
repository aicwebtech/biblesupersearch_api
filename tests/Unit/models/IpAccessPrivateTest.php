<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\IpAccess;


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


        // if($no_limit) {
        //     $this->assertEquals(0, $IP->getAccessLimit());
        //     $this->assertTrue($IP->hasUnlimitedAccess());
        // } else {
        //     // Speed up this test by setting the current count to limit - 5
        //     $Log = $IP->getAccessLog();
        //     $Log->count = $default_limit - 5;
        //     $Log->save();

        //     $this->assertEquals($default_limit - 5, $IP->getDailyHits());

        //     for($i = 1; $i < 5; $i ++) {
        //         $IP->incrementDailyHits();
        //     }

        //     $this->assertFalse($IP->isLimitReached());
        //     // Next hit will push it over the limit
        //     $this->assertTrue( $IP->incrementDailyHits() );
        //     $this->assertTrue($IP->isLimitReached());
        //     $this->assertFalse( $IP->incrementDailyHits() );
        // }

        // $IP->delete();
    }

    public function testNoLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);
        $limit = 0;
        $IP->limit = $limit;
        $IP->save();

        $this->helper($IP, $limit);

        // $this->assertEquals($limit, $IP->getAccessLimit());
        // $this->assertEquals(0, $IP->getDailyHits());
        // $this->assertTrue($IP->hasUnlimitedAccess());

        // $IP->incrementDailyHits();
        // $this->assertEquals(1, $IP->getDailyHits());
        // $this->assertFalse($IP->isLimitReached());

        // $this->assertFalse($IP->isLimitReached());
        // $IP->incrementDailyHits();
        // $IP->delete();
    }    

    public function testCustomLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip);
        $limit = 20;
        $IP->limit = $limit;
        $IP->save();

        $this->helper($IP, $limit);

        // $this->assertEquals($limit, $IP->getAccessLimit());
        // $this->assertEquals(0, $IP->getDailyHits());

        // $IP->incrementDailyHits();
        // $this->assertEquals(1, $IP->getDailyHits());
        // $this->assertFalse($IP->isLimitReached());

        // for($hits = 2; $hits < $limit; $hits ++) {
        //     $IP->incrementDailyHits();
        // }

        // $this->assertFalse($IP->isLimitReached());
        // // Next hit will push it over the limit
        // $this->assertTrue( $IP->incrementDailyHits() );
        // $this->assertTrue($IP->isLimitReached());
        // $this->assertFalse( $IP->incrementDailyHits() );
        // $IP->delete();
    }

    public function testDomainCustomLimit() 
    {
        $ip = $this->_fakeIp();
        $IP = IpAccess::findOrCreateByIpOrDomain($ip, 'testdomaincustomlimit.com');
        $limit = 125;
        $IP->limit = $limit;
        $IP->save();

        $this->helper($IP, $limit);

        // $this->assertEquals($limit, $IP->getAccessLimit());
        // $this->assertEquals(0, $IP->getDailyHits());

        // $IP->incrementDailyHits();
        // $this->assertEquals(1, $IP->getDailyHits());
        // $this->assertFalse($IP->isLimitReached());

        // for($hits = 2; $hits < $limit; $hits ++) {
        //     $IP->incrementDailyHits();
        // }

        // $this->assertFalse($IP->isLimitReached());
        // $IP->incrementDailyHits();
        // $this->assertTrue($IP->isLimitReached());
        // $IP->delete();
    }

    protected function helper($IP, $limit) 
    {
        $this->assertNotEquals($limit, $IP->getAccessLimit());
        $this->assertLessThan(0, $IP->getAccessLimit());;
        $this->assertEquals(0, $IP->getDailyHits());

        $this->assertFalse( $IP->incrementDailyHits() );
        $this->assertEquals(0, $IP->getDailyHits());
        $this->assertFalse($IP->isLimitReached());

        $this->assertTrue($IP->isAccessRevoked());
        $IP->delete();
    }

    public function testHostParsing() 
    {
        $hosts = array(
            ['https://www.example.com/bible-search', 'example.com'],
            ['http://example.com/bible-search', 'example.com'],
            ['https://bible.example.com', 'bible.example.com'],
            ['https://bible.example.com/', 'bible.example.com'],
            ['http://search.bible.example.com/page/1', 'search.bible.example.com'],
            ['https://example.com/bible/?biblesupersearch_ingerface=Classic', 'example.com'],
            ['https://example.com/bible/search.html', 'example.com'],
            ['bib.example.com', 'bib.example.com'],
            ['http://study.search.bible.example.com/index.php', 'study.search.bible.example.com'],
            ['http://study.search.bible.example.com/index.php', 'study.search.bible.example.com'],
            ['example.org/grace-and-truth-came-through-jesus-christ-john-117/?fbclid=IwAR2bxtJmK9JKbhBY-Pznbf4NCGOjpsQ0ju6g05lXX6_XWIPj7h95tF4', 'example.org'],
            ['bib.example.com:7070/bible-tool/?customize_changeset_uuid=660a376f-ab87-41b1-b77a-489cae0a41d0', 'bib.example.com'],
            ['localhost', NULL],
            ['localhost:3333', NULL],
            ['http://top.prod.example.com/#/c/60fzq9yftx', 'top.prod.example.com'],
            ['http://top.prod.example.com#stuff', 'top.prod.example.com'],
            ['m.example.com/from=1000539d/s?word=supersearch%E5%96%B5%E5%96%B5%E5%96%B5&sa=ts_1&ts=6361105&t_kt=0&ie=utf-8&rsv_t=a6a1yI%252FfKJzP3GLcp13GTpYd5YT2WEGUGGcPoDu6ZHXMAE2pj5MMuUTitCaHKns&rsv_pq=10848949574623648659&ss=100&tj=1&rq=supersearch&rqlang=zh&rsv_sug4=', 'm.example.com'],
        );

        foreach($hosts as $h) {
            $domain = IpAccess::parseDomain($h[0]);
            $this->assertEquals($domain, $h[1]);
        }
    }

    public function testSameDomain() 
    {
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
