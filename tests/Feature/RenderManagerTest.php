<?php

//namespace Tests\Feature;

//use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\RenderManager;

class RenderManagerTest extends TestCase {

    public function testList() {
        $list = RenderManager::getRendererList();

        $this->assertArrayHasKey('text', $list);
        $this->assertArrayHasKey('pdf', $list);

        $this->assertArrayHasKey('name', $list['text']);
        $this->assertArrayHasKey('name', $list['pdf']);
        $this->assertArrayHasKey('desc', $list['text']);
        $this->assertArrayHasKey('desc', $list['pdf']);
    }

    public function testRender() {
        $TextRender = new \App\Renderers\PlainText('kjv');
        $success = $TextRender->render(TRUE);

        $this->assertTrue($success);
        $this->assertFalse($TextRender->hasErrors());
    }
}
