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
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results']['books'][44]['name']); 
        $this->assertEquals('KJV', $response['results']['bibles']['kjv']['shortname']); 
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);

        // POST
        $response = $this->postJson('/api/statics', ['language' => 'es']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results']['books'][44]['name']);
        $this->assertEquals('KJV', $response['results']['bibles']['kjv']['shortname']); 
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
    }       

    /**
     * Tests of the 'bibles' action
     * Note: the UI doesn't actually use this action
     *
     * @return void
     */
    public function testActionBibles()
    {
        // GET
        $response = $this->getJson('/api/bibles?language=es');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('KJV', $response['results']['kjv']['shortname']); 

        // POST
        $response = $this->postJson('/api/bibles', ['language' => 'es']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('KJV', $response['results']['kjv']['shortname']); 
    }   

    /**
     * Tests of the 'statics' action
     *
     * @return void
     */
    public function testActionBooks()
    {
        // GET
        $response = $this->getJson('/api/books?language=es');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results'][44]['name']);

        // POST
        $response = $this->postJson('/api/books', ['language' => 'es']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals('Romanos', $response['results'][44]['name']);
    }    

    /**
     * Tests of the default ('query') action
     *
     * @return void
     */
    public function testActionDefaultQuery()
    {
        // GET - empty request
        $response = $this->getJson('/api');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api?request=faith&bible=kjv');
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);

        // POST
        $response = $this->postJson('/api', ['request' => 'faith', 'bible' => 'kjv']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);
    }    

    /**
     * Tests of the 'query' action
     *
     * @return void
     */
    public function testActionQuery()
    {
        // GET - empty request
        $response = $this->getJson('/api/query');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/query');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);

        // GET
        $response = $this->getJson('/api/query?request=faith&bible=kjv');
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);

        // POST
        $response = $this->postJson('/api/query', ['request' => 'faith', 'bible' => 'kjv']);
        $response->assertStatus(200);
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(338, $response['paging']['total']);
    }    

    /**
     * Tests of the 'version' action
     *
     * @return void
     */
    public function testVersionAction()
    {
        // GET
        $response = $this->getJson('/api/version');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
        
        // POST
        $response = $this->postJson('/api/version');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        $this->assertEquals(config('app.version'), $response['results']['version']);
        $this->assertEquals(config('app.name'), $response['results']['name']);
    }    

    /**
     * Tests of the 'strongs' action
     *
     * @return void
     */
    public function testStrongsAction()
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

    /**
     * Tests of the 'download' action
     *
     * @return void
     */
    public function testDownloadAction()
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // GET - empty request
        $response = $this->getJson('/api/download');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/download');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);        

        // GET - empty request, pretty_print errors
        $response = $this->get('/api/download?pretty_print=true');
        $response->assertStatus(200); // should this be so?        
        
        // POST - empty request, pretty_print errors
        $response = $this->postJson('/api/download', ['pretty_print' => TRUE]);
        $response->assertStatus(200);        

        // Attempting to test actual file download results in "headers already sent" errors, unable to test here!

        // GET
        // $response = $this->getJson('/api/download?bible=kjv&format=csv');
        // $response->assertStatus(200);        
        
        // POST
        // $response = $this->postJson('/api/download', ['bible' => 'kjv', 'format' => 'csv']);
        // $response->assertStatus(200);        
    }

    /**
     * Tests of the 'render_needed' action
     *
     * @return void
     */
    public function testRenderNeededAction()
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // GET - empty request
        $response = $this->getJson('/api/render_needed');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/render_needed');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);        

        // GET
        $response = $this->getJson('/api/render_needed?bible=kjv&format=csv');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        
        // POST
        $response = $this->postJson('/api/render_needed', ['bible' => 'kjv', 'format' => 'csv']);

        // attempt to trap intermittint error
        if($response->getStatusCode() != 200) {
            var_dump($response['error_level']);
            var_dump($response['errors']);
            var_dump($response['results']);
            $this->assertEquals(1, $response['error_level']);
            $this->assertEquals(TRUE, $response['results']['render_needed']);
        }
        else {
            $response->assertStatus(200);        
        }

        $this->assertEquals(0, $response['error_level']);
    }    

    /*
     * This tests the render_needed flag on the render action
     */
    public function testRenderNeededFlag() {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // Test NOT needing render

        // Render a file.  
        $Renderer = new \App\Renderers\MachineReadableText('kjv');
        $Renderer->renderIfNeeded();
        $RR = $Renderer->_getRenderingRecord();
        $file_path = $RR->getRenderedFilePath();

        $this->assertFalse($Renderer->hasErrors());
        $this->assertFileExists($file_path);

        $response = $this->postJson('/api/render_needed', ['bible' => 'kjv', 'format' => 'mr_text']);

        $response->assertStatus(200);      
        $this->assertEquals(0, $response['error_level']);
        $this->assertFalse($response['results']['render_needed']);

        // Test needing render

        // Delete the rendered file
        $Renderer->deleteRenderFile();
        $this->assertFileDoesNotExist($file_path);

        $response = $this->postJson('/api/render_needed', ['bible' => 'kjv', 'format' => 'mr_text']);

        // Yes, this is returned as an 'error'
        $response->assertStatus(400);      
        $this->assertEquals(1, $response['error_level']);
        $this->assertTrue($response['results']['render_needed']);
    }

    /**
     * Tests of the 'render' action
     *
     * @return void
     */
    public function testRenderAction()
    {
        if(!config('download.enable')) {
            $this->markTestSkipped('Downloads disabled');
        }

        // GET - empty request
        $response = $this->getJson('/api/render');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);
        
        // POST - empty request
        $response = $this->postJson('/api/render');
        $response->assertStatus(400);        
        $this->assertEquals(4, $response['error_level']);        

        // GET
        $response = $this->getJson('/api/render?bible=kjv&format=csv');
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
        
        // POST
        $response = $this->postJson('/api/render', ['bible' => 'kjv', 'format' => 'csv']);
        $response->assertStatus(200);        
        $this->assertEquals(0, $response['error_level']);
    }

}
