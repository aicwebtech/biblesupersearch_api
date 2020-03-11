<?php

// namespace Tests\Feature;

// use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use aicwebtech\BibleSuperSearch\Engine;

class AppTest extends TestCase {

    public function testPremiumDisableConfig() {
        $this->assertFalse( config('app.premium_disabled'), 'Config app.premium_disabled must be FALSE in production');
    }

}
