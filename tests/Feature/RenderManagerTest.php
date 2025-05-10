<?php

//namespace Tests\Feature;

//use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\RenderManager;
use App\Models\RenderLog;
use PHPUnit\Framework\Attributes\Depends;

// php ./vendor/phpunit/phpunit/phpunit --filter=RenderManagerTest

class RenderManagerTest extends TestCase 
{
    private $skip_render_tests = TRUE;

    public function testList() 
    {
        $list = RenderManager::getRendererList();

        $this->assertArrayHasKey('text', $list);
        $this->assertArrayHasKey('pdf', $list);

        $this->assertArrayHasKey('name', $list['text']);
        $this->assertArrayHasKey('name', $list['pdf']);
        $this->assertArrayHasKey('desc', $list['text']);
        $this->assertArrayHasKey('desc', $list['pdf']);
    }

    public function testZipFileCleanup()
    {
        $deleted = RenderManager::cleanUpTempZipFiles(true);

        $base_path = \App\Renderers\RenderAbstract::getRenderBasePath();

        $threshold = 3600;

        $this->assertIsArray($deleted);

        foreach($deleted as $file) {
            $this->assertMatchesRegularExpression('/^truth_\d{8}_\d{6}_\d{6}\.zip$/', $file);
            $mtime = filectime($base_path . $file);
            $this->assertLessThanOrEqual(time() - $threshold, $mtime);
        }

        $threshold = 300;
        $fake_ip = '275.999.123.456';

        $lts = [
            strtotime('-400 seconds'),
            strtotime('-200 seconds'),
            strtotime('-' . rand(0, 1000) . ' seconds'),
            strtotime('-' . rand(0, 1000) . ' seconds'),
            strtotime('-' . rand(0, 1000) . ' seconds'),
        ];

        foreach($lts as $ts) {
            $L = new RenderLog();
            $L->ip_address = $fake_ip;
            $L->module = 'ALL';
            $L->filename = 'truth_' . date('Ymd_His_u', $ts) . '.zip';
            $L->created_at = date('Y-m-d H:i:s', $ts);
            $L->save();
        }

        $deleted = RenderLog::deleteByIp($fake_ip, 300, true);

        $this->assertIsArray($deleted);

        foreach($deleted as $file) {
            $this->assertMatchesRegularExpression('/^truth_\d{8}_\d{6}_\d{6}\.zip$/', $file);

            preg_match('/^truth_(\d{8})_(\d{6})_\d{6}\.zip$/', $file, $matches);

            $ts = strtotime($matches[1] . ' ' . $matches[2]);

            $this->assertLessThanOrEqual(time() - $threshold, $ts);
            
            if(!is_file($base_path . $file)) {
                continue; // Already deleted
            }
            
            $mtime = filectime($base_path . $file);
            $this->assertLessThanOrEqual(time() - $threshold, $mtime);
        }

        RenderLog::deleteByIp($fake_ip, 0); // Clean up
    }

    public function testFileCleanUpCalcs() 
    {
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

    public function testRetainConfigsInit() 
    {
        $test_space = 50;
        
        $test_params = [
            'cache_size'        => 200,
            'temp_cache_size'   => 100,
            'cur_space'         => 150,
            'min_hits'          => 0,
            'days'              => 10,
        ];

        $TextRender = new \App\Renderers\PlainText('kjv');
        $success = $TextRender->renderIfNeeded();        
        $this->assertTrue($success);

        $TextRender->deleteRenderFile();
        $this->assertTrue($TextRender->isRenderNeeded(TRUE), 'file deleted, should need render here');

        $success = $TextRender->renderIfNeeded();
        $this->assertTrue($success);

        $this->assertFalse($TextRender->isRenderNeeded(TRUE), 'Already rendered, shoudnt need it here ' . __LINE__);
        
        $file_path = $TextRender->getRenderFilePath();
        $this->assertFileExists($file_path);

        $Rendering = $TextRender->_getRenderingRecord();
        $Rendering->downloaded_at = date('Y-m-d H:i:s');
        $Rendering->save();
        $Rendering->refresh();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, $test_params);
        $freed_space_raw = $results['freed_space'];

        return compact('test_space', 'test_params', 'freed_space_raw', 'Rendering', 'file_path');
    }


    /**
     * Testing max filesize.  Anything larger will be deleted
     * 
     */ 

    #[Depends('testRetainConfigsInit')]
    public function testRetainMaxFilesize($shared) 
    {
        extract($shared);

        $Rendering->file_size = 10;
        $Rendering->save();
        $Rendering->refresh();
        $this->assertEquals(10, $Rendering->file_size);

        // Too much
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['max_filesize' => 9]));
        $this->assertContains($file_path, $results['deleted_files']);;        

        // Same
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['max_filesize' => 10]));
        $this->assertNotContains($file_path, $results['deleted_files']);      

        // Plenty
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['max_filesize' => 11]));
        $this->assertNotContains($file_path, $results['deleted_files']);;

        // Unlimited space
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['max_filesize' => 0]));
        $this->assertNotContains($file_path, $results['deleted_files']);;

        $shared['Rendering'] = $Rendering;
        return $shared;
    }

    /**
     * Testing days to retain
     * 
     */ 
    #[Depends('testRetainMaxFilesize')]
    public function testRetainDays($shared) 
    {
        extract($shared);
        
        // Not yet
        $Rendering->downloaded_at = date('Y-m-d H:i:s', strtotime('-9 days'));
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, []));
        $this->assertNotContains($file_path, $results['deleted_files']);      

        // It happens today, but later
        $Rendering->downloaded_at = date('Y-m-d H:i:s', strtotime('-10 days'));
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, []));
        $this->assertNotContains($file_path, $results['deleted_files']);      

        // Expired!
        $Rendering->downloaded_at = date('Y-m-d H:i:s', strtotime('-11 days'));
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, []));
        $this->assertContains($file_path, $results['deleted_files']);;

        // Unlimited time
        $Rendering->downloaded_at = date('Y-m-d H:i:s', strtotime('-1100 days'));
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['days' => 0]));
        $this->assertNotContains($file_path, $results['deleted_files']);;

        $Rendering->downloaded_at = date('Y-m-d H:i:s');
        $Rendering->save();

        $shared['Rendering'] = $Rendering;
        return $shared;
    }

    /**
     * Testing Minimum rendering time
     * 
     */ 
    #[Depends('testRetainDays')]
    public function testRetainMinimumRenderTime($shared) 
    {
        extract($shared);
        $cached = $Rendering->rendered_duration;

        $Rendering->rendered_duration = 15;
        $Rendering->save();
        $this->assertEquals(15, $Rendering->rendered_duration);

        // These do not get deleted
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 5]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 10]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 14]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 15]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        // These do get deleted
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 16]));
        $this->assertContains($file_path, $results['deleted_files']);
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 20]));
        $this->assertContains($file_path, $results['deleted_files']);
        
        // Unlimited
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 0]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        // What if it's 0?
        $Rendering->rendered_duration = 0;
        $this->assertEquals(0, $Rendering->rendered_duration);
        $Rendering->save();

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 1]));
        $this->assertContains($file_path, $results['deleted_files']);

        // Only retainted if Unlimited
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_render_time' => 0]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        $Rendering->rendered_duration = $cached;
        $Rendering->save();
        $shared['Rendering'] = $Rendering;
        return $shared;
    }    

    /**
     * Testing Minimum hits
     * 
     */ 
    #[Depends('testRetainMinimumRenderTime')]
    public function testRetainMinimumHits($shared) 
    {
        extract($shared);

        $cached = $Rendering->hits; // this is prob 0 if non-production build

        $Rendering->hits = 45;
        $Rendering->save();

        // These don't get deleted
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 40]));
        $this->assertNotContains($file_path, $results['deleted_files']);; 
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 44]));
        $this->assertNotContains($file_path, $results['deleted_files']);       
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 45]));
        $this->assertNotContains($file_path, $results['deleted_files']);       

        // These get deleted
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 46]));
        $this->assertContains($file_path, $results['deleted_files']);; 
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 50]));
        $this->assertContains($file_path, $results['deleted_files']);; 

        // Unlimited
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 0]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        // What if it's 0?
        $Rendering->hits = 0;
        $Rendering->save();
        $this->assertEquals(0, $Rendering->hits);

        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 1]));
        $this->assertContains($file_path, $results['deleted_files']);;

        // Only retainted if Unlimited
        $results = RenderManager::_testCleanUpFiles($test_space, FALSE, array_replace($test_params, ['min_hits' => 0]));
        $this->assertNotContains($file_path, $results['deleted_files']);

        $this->assertTrue(TRUE);

        $Rendering->hits = $cached;
        $Rendering->save();

        $shared['Rendering'] = $Rendering;
        return $shared;
    }

    public function testRenderNeeded() 
    {

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

    /* Methods below are really slow, and should not be called in production */

    public function testManagerRender() 
    {

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
}
