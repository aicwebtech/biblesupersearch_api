<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Engine;

class EngineTest extends TestCase
{
    public function testInstance() {
        $engine = new Engine();
        $this->assertInstanceOf('App\Engine', $engine);
    }
    
    public function testMethodAddBible() {
        $engine = new Engine();
        $engine->addBible('kjv');
        $this->assertFalse($engine->hasErrors());
        $Bibles = $engine->getBibles();
        $this->assertInstanceOf('App\Models\Bible', $Bibles['kjv']);
    }
    
    public function testMethodSetBibles() {
        $engine = new Engine();
        $engine->setBibles(['kjv', 'tr', 'tyndale', 'luther']);
        $this->assertFalse($engine->hasErrors());
        $Bibles = $engine->getBibles();
        $this->assertCount(4, $Bibles);
    }
    
    public function testFalseBible() {
        $engine = new Engine();
        $engine->addBible('niv');
        $this->assertTrue($engine->hasErrors());
        $errors = $engine->getErrors();
        $this->assertEquals("Bible module 'niv' not found", $errors[0]);
    }
}
