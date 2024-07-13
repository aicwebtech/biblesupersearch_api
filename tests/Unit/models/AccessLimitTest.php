<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\ApiAccessLevel;

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
}
