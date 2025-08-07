<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function testConfigExists(): void 
    {
        $this->assertTrue(class_exists(\Config::class), 'Config class does not exist');
        // $this->assertTrue(method_exists(\Config::class, 'get'), 'Config::get method does not exist');
        // $this->assertTrue(method_exists(\Config::class, 'set'), 'Config::set method does not exist');
    }
}
