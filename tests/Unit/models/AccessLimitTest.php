<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\ApiAccessLevel;
use App\ApiAccessManager;
use App\ConfigManager;

class AccessLimitTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testApiAccessLevelLimit() 
    {
        // NO Access
        $Level = ApiAccessLevel::find(1);
        $this->assertFalse($Level->hasBasicAccess());
        $this->assertTrue($Level->hasNoAccess());
        $this->assertEquals(-1, $Level->limit);

        // Basic Access (default access limit)
        $Level = ApiAccessLevel::find(2);
        $this->assertFalse($Level->hasNoAccess());
        $this->assertTrue($Level->hasBasicAccess());
        $this->assertEquals(null, $Level->limit);

        // Full Access - unlimited access
        $Level = ApiAccessLevel::find(4);
        $this->assertFalse($Level->hasNoAccess());
        $this->assertTrue($Level->hasBasicAccess());
        $this->assertEquals(0, $Level->limit);
    }

    public function _testWhitelist()
    {
        // These assertions are having async/db issues (ie configs setting before the assertions causing tests to collide)

        $cache = [
            'bss.daily_access_limit'     => config('bss.daily_access_limit'),
            'bss.daily_access_whitelist' => config('bss.daily_access_whitelist'),
        ];

        ConfigManager::setConfigs([
            'bss.daily_access_limit'     => 2000,
            'bss.daily_access_whitelist' => '',
        ]);

        $this->assertEmpty(config('bss.daily_access_whitelist'));
        $this->assertFalse(ApiAccessManager::isWhitelisted(null, 'example.com'));

        ConfigManager::setConfigs([
            'bss.daily_access_whitelist' => 'otherwebsite.net',
        ]);

        $this->assertFalse(ApiAccessManager::isWhitelisted(null, 'example.com'));

        ConfigManager::setConfigs([
            'bss.daily_access_whitelist' => "otherwebsite.net\rexample.com",
        ]);

        $this->assertTrue(ApiAccessManager::isWhitelisted(null, 'example.com'));

        ConfigManager::setConfigs($cache);
    }
}
