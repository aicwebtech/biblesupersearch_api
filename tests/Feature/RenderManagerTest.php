<?php

//namespace Tests\Feature;

//use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\RenderManager;

// php ./vendor/phpunit/phpunit/phpunit --filter=RenderManagerTest

class RenderManagerTest extends TestCase {
    private $skip_render_tests = TRUE;

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

    public function testDaysToRetain() {
        $test_space = 50;
        
        $test_params = [
            'cache_size'        => 200,
            'temp_cache_size'   => 100,
            'cur_space'         => 150,
            'min_hits'          => 0,
            'days'              => 10,
        ];

        $TextRender = new \App\Renderers\PlainText('kjv');
        $Rendering0 = $TextRender->_getRenderingRecord();
        $success = $TextRender->renderIfNeeded();        
        $this->assertTrue($success);
        $this->assertFalse($TextRender->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here ' . __LINE__);

        $Rendering = $TextRender->_getRenderingRecord();
        $Rendering->downloaded_at = date('Y-m-d H:i:s');
        $Rendering->save();
        $Rendering->refresh();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, $test_params);
        $freed_space_raw = $results['freed_space'];

        // Testing max filesize
        $Rendering->file_size = 10;
        $Rendering->save();
        $Rendering->refresh();
        $this->assertEquals(10, $Rendering->file_size);

        // Too much
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['max_filesize' => 9]));
        $this->assertEquals($freed_space_raw + 10, $results['freed_space']);        

        // Same
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['max_filesize' => 10]));
        $this->assertEquals($freed_space_raw, $results['freed_space']);        

        // Plenty
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['max_filesize' => 11]));
        $this->assertEquals($freed_space_raw, $results['freed_space']);

        // Testing days to retain
        
        // Not yet
        $Rendering->downloaded_at = date('Y-m-d H:i:s', strtotime('-9 days'));
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, []));
        $this->assertEquals($freed_space_raw, $results['freed_space']);        

        // It happens today, but later
        $Rendering->downloaded_at = date('Y-m-d H:i:s', strtotime('-10 days'));
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, []));
        $this->assertEquals($freed_space_raw, $results['freed_space']);        

        // Expired!
        $Rendering->downloaded_at = date('Y-m-d H:i:s', strtotime('-11 days'));
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, []));
        $this->assertEquals($freed_space_raw + 10, $results['freed_space']);

        // Minimum rendering time

        // Minimum hits
    }

    public function testRenderNeeded() {
        // if($this->skip_render_tests) {
        //     // $this->markTestSkipped('Rendering tests skipped to save time');\            
        //     $this->assertTrue(TRUE);
        //     return;
        // }
        
        $TextRender = new \App\Renderers\PlainText('kjv');
        $Rendering0 = $TextRender->_getRenderingRecord();
        $success = $TextRender->renderIfNeeded();        
        $this->assertTrue($success);
        $Rendering1 = $TextRender->_getRenderingRecord(TRUE);

        $render_file_path = $TextRender->getRenderFilePath();
        $render_file_path_alt = $render_file_path . '.alt';

        $meta_hash = md5($TextRender->_getMetaString());

        $this->assertEquals($Rendering1->meta_hash, $meta_hash);
        $this->assertEquals($Rendering0->meta_hash, $meta_hash);

        $this->assertFalse($TextRender->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here ' . __LINE__);

        $Rendering = $TextRender->_getRenderingRecord();
        $cache = $Rendering->attributesToArray();

        $this->assertFalse($TextRender->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here either ' . __LINE__);

        $Rendering->version --;
        $Rendering->save();

        $this->assertTrue($TextRender->isRenderNeeded(TRUE), 'version number changed on rendering, should need render here');

        $Rendering->version ++;
        $Rendering->save();
        $this->assertFalse($TextRender->isRenderNeeded(TRUE), 'changed version number back, no rendering needed');

        $Rendering->meta_hash = md5('Holy Bible, Public Domain Version');
        $Rendering->save();
        
        $this->assertTrue($TextRender->isRenderNeeded(TRUE), 'meta hash changed on rendering, should need render here');

        $Rendering->meta_hash = $cache['meta_hash'];
        $Rendering->save();

        $this->assertFalse($TextRender->isRenderNeeded(TRUE), 'changed meta hash number back, no rendering needed');

        rename($render_file_path, $render_file_path_alt);

        $this->assertTrue($TextRender->isRenderNeeded(), 'file does not exist, should need render here');
        
        rename($render_file_path_alt, $render_file_path);

        $this->assertFalse($TextRender->isRenderNeeded(TRUE), 'file is back, no rendering needed');
    }

    /* Methods below should not be called in production */

    public function testManagerRender() {

        if($this->skip_render_tests) {
            $this->assertTrue(TRUE);
            return;
        }

        // return;

        // $Manager = new RenderManager(['kjv'], 'pdf');
        // $Manager = new RenderManager(['kjv', 'rvg'], 'pdf');
        // $Manager = new RenderManager(['svd','wlc'], 'pdf');
        $Manager = new RenderManager(['chinese_union_simp'], 'pdf');
        // $Manager = new RenderManager(['chinese_union'], 'pdf');
        // $Manager = new RenderManager(['kjv', 'rvg'], 'pdf');
        // $Manager = new RenderManager(['kjv', 'rvg', 'svd', 'thaikjv', 'synodal', 'tr', 'wlc','bkr', 'stve', 'cornilescu', 'chinese_union'], 'pdf');

        $success = $Manager->render(TRUE, TRUE, TRUE);

        $this->assertTrue($success);

        if(!$success) {
            print_r($Manager->getErrors());
        }

    }

    public function testFileCleanUp() {

        if($this->skip_render_tests) {
            $this->assertTrue(TRUE);
            return;
        }

        return;
        // RenderManager::_testCleanUpFiles(130);

        RenderManager::_testCleanUpFiles(120, FALSE, [
            'cache_size' => 200,
            'temp_cache_size' => 100,
        ]);
        
        $this->assertTrue(TRUE);
    }
}
