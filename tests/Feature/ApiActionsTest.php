<?php

//namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
//use Tests\TestCase;

class ApiActionsTest extends TestCase
{
    /**
     * Tests of the 'statics' action
     *
     * @return void
     */
    public function testActionStatics()
    {
        // GET
        $response = $this->getJson('/api/statics?language=es');
        $response->assertStatus(200);        
        $this->assertEquals('Romanos', $response['results']['books'][44]['name']); /// ?????
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
        $this->assertEquals(0, $response['error_level']);

        // POST
        $response = $this->postJson('/api/statics', ['language' => 'es']);
        $response->assertStatus(200);
        $this->assertEquals('Romanos', $response['results']['books'][44]['name']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
        $this->assertEquals(0, $response['error_level']);
    }    

    /**
     * Tests of the 'query' action
     *
     * @return void
     */
    public function testActionQuery()
    {
        // GET - empty request
        $response = $this->getJson('/api');
        $this->assertEquals(4, $response['error_level']);
        $response->assertStatus(400);        
        
        // POST - empty request
        $response = $this->postJson('/api');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api?request=faith');
        $response->assertStatus(200);
        $this->assertEquals(338, $response['paging']['total']);
        $this->assertEquals(0, $response['error_level']);

        // POST
        $response = $this->postJson('/api', ['request' => 'faith']);
        $response->assertStatus(200);
        $this->assertEquals(338, $response['paging']['total']);
        $this->assertEquals(0, $response['error_level']);
    }    

    /**
     * Tests of the 'version' action
     *
     * @return void
     */
    public function testVerionQuery()
    {
        // GET
        $response = $this->getJson('/api/version');
        $response->assertStatus(200);        
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
        $this->assertEquals(0, $response['error_level']);
        
        // POST
        $response = $this->postJson('/api/version');
        $response->assertStatus(200);        
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
        $this->assertEquals(0, $response['error_level']);
    }    

    /**
     * Tests of the 'strongs' action
     *
     * @return void
     */
    public function testStrongsQuery()
    {
        // GET - empty request
        $response = $this->getJson('/api/strongs');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/strongs');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api/strongs?strongs=H1234');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(1234, $response['results'][0]['id']);
        
        // POST
        $response = $this->postJson('/api/strongs', ['strongs' => 'H1234']);
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(1234, $response['results'][0]['id']);
    }
}
