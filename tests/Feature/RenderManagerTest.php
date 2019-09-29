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

        // print_r($list);
    }

    public function testDirectRender() {
        if($this->skip_render_tests) {
            $this->markTestSkipped('Rendering tests skipped to save time');
        }

        $TextRender = new \App\Renderers\PlainText('kjv');
        $success = $TextRender->render(TRUE);

        $this->assertTrue($success);
        $this->assertFalse($TextRender->hasErrors());
    }

    public function testManagerRender() {
        if($this->skip_render_tests) {
            $this->markTestSkipped('Rendering tests skipped to save time');
        }

        // $Manager = new RenderManager(['kjv'], 'pdf');
        // $Manager = new RenderManager(['chinese_union'], 'pdf');
        // $Manager = new RenderManager(['kjv', 'rvg'], 'pdf');
        $Manager = new RenderManager(['kjv', 'rvg', 'svd', 'thaikjv', 'synodal', 'tr', 'wlc','bkr', 'stve', 'cornilescu', 'chinese_union'], 'pdf');

        $success = $Manager->render(TRUE, TRUE, TRUE);

        if(!$success) {
            print_r($Manager->getErrors());
        }

        $this->assertTrue($success);
        $this->assertFalse($Manager->hasErrors());
    }
}
