<?php

//namespace Tests\Feature;

//use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\RenderManager;

class RenderManagerTest extends TestCase {
    private $skip_render_tests = FALSE;

    public function testList() {
        $list = RenderManager::getRendererList();

        $this->assertArrayHasKey('text', $list);
        $this->assertArrayHasKey('pdf', $list);

        $this->assertArrayHasKey('name', $list['text']);
        $this->assertArrayHasKey('name', $list['pdf']);
        $this->assertArrayHasKey('desc', $list['text']);
        $this->assertArrayHasKey('desc', $list['pdf']);
    }

    public function testFileCleanUpCalcs() {
        $verbose = FALSE;

        // Test 1: Current space exceeds MAXIMUM cache size max (temp + retained) needed render space exceeds temp cache size
        $results = RenderManager::_testCleanUpFiles(120, $verbose, [
            'cache_size'        => 200,
            'temp_cache_size'   => 100,
            'cur_space'         => 350,
        ]);

        $this->assertEquals(170, $results['space_needed_overall']);        

        // Test 2: Current space exceeds cache size, needed render space exceeds temp cache size
        $results = RenderManager::_testCleanUpFiles(130, $verbose, [
            'cache_size'        => 200,
            'temp_cache_size'   => 100,
            'cur_space'         => 250,
        ]);

        $this->assertEquals(80, $results['space_needed_overall']);           

        // Test 3: Current space exceeds cache size, needed render space is less than temp cache size
        $results = RenderManager::_testCleanUpFiles(150, $verbose, [
            'cache_size'        => 300,
            'temp_cache_size'   => 200,
            'cur_space'         => 350,
        ]);

        $this->assertEquals(50, $results['space_needed_overall']);         

        // Test 4: Current space less than cache size, needed render space is less than temp cache size
        $results = RenderManager::_testCleanUpFiles(150, $verbose, [
            'cache_size'        => 300,
            'temp_cache_size'   => 200,
            'cur_space'         => 250,
        ]);        

        $this->assertEquals(0, $results['space_needed_overall']);           

        // Test 5: Current space less than cache size, needed render space exceeds temp cache size, needed render space + current space exceeds maximum cache size
        $results = RenderManager::_testCleanUpFiles(350, $verbose, [
            'cache_size'        => 300,
            'temp_cache_size'   => 200,
            'cur_space'         => 250,
        ]);

        $this->assertEquals(100, $results['space_needed_overall']);           

        // Test 6: Current space less than cache size, needed render space exceeds temp cache size, but needed render space + current space does not exceed maximum cache size
        $results = RenderManager::_testCleanUpFiles(150, $verbose, [
            'cache_size'        => 300,
            'temp_cache_size'   => 100,
            'cur_space'         => 250,
        ]);

        $this->assertEquals(0, $results['space_needed_overall']);   
    }

    /* Methods below should not be called in production */

    public function testDirectRender() {
        return;

        if($this->skip_render_tests) {
            $this->markTestSkipped('Rendering tests skipped to save time');
        }

        $TextRender = new \App\Renderers\PlainText('kjv');
        $success = $TextRender->render(TRUE);        
        $TextRender = new \App\Renderers\MachineReadableText('kjv');
        $success = $TextRender->render(TRUE);

        $this->assertTrue($success);
        $this->assertFalse($TextRender->hasErrors());
    }

    public function testManagerRender() {
        return;

        if($this->skip_render_tests) {
            $this->markTestSkipped('Rendering tests skipped to save time');
        }

        $Manager = new RenderManager(['kjv'], 'pdf');
        // $Manager = new RenderManager(['chinese_union'], 'pdf');
        // $Manager = new RenderManager(['kjv', 'rvg'], 'pdf');
        // $Manager = new RenderManager(['kjv', 'rvg', 'svd', 'thaikjv', 'synodal', 'tr', 'wlc','bkr', 'stve', 'cornilescu', 'chinese_union'], 'pdf');

        $success = $Manager->render(TRUE, TRUE, TRUE);

        if(!$success) {
            print_r($Manager->getErrors());
        }

        $this->assertTrue($success);
        $this->assertFalse($Manager->hasErrors());
    }

    public function testFileCleanUp() {
        return;

        if($this->skip_render_tests) {
            $this->markTestSkipped('Rendering tests skipped to save time');
        }

        // RenderManager::_testCleanUpFiles(130);

        RenderManager::_testCleanUpFiles(120, FALSE, [
            'cache_size' => 200,
            'temp_cache_size' => 100,
        ]);
        
        $this->assertTrue(TRUE);
    }
}
