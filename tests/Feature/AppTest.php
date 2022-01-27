<?php

// namespace Tests\Feature;

// use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Engine;

class AppTest extends TestCase {

    public function testPremiumDisableConfig() {
        $this->assertTrue(TRUE);
        $env = env('APP_ENV', 'production');

        if($env == 'production') {
            $this->assertFalse( config('app.premium_disabled'), 'Config app.premium_disabled must be FALSE in production');
        }
    }

}
