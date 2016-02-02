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
        $this->assertEquals("Bible text 'niv' not found", $errors[0]);
    }
    
    public function testBasicSearch() {
        // NOT whole word searches!
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith']);
        $this->assertCount(338, $results['kjv']);
        $this->assertEquals(4,  $results['kjv'][0]->book);
        $this->assertEquals(12, $results['kjv'][0]->chapter);
        $this->assertEquals(7,  $results['kjv'][0]->verse);
        $this->assertEquals('My servant Moses is not so, who is faithful in all mine house.',  $results['kjv'][0]->text);
        $this->assertEquals(51, $results['kjv'][201]->book);
        $this->assertEquals(1,  $results['kjv'][201]->chapter);
        $this->assertEquals(4,  $results['kjv'][201]->verse);
        $this->assertEquals('Since we heard of your faith in Christ Jesus, and of the love which ye have to all the saints,',  $results['kjv'][201]->text);
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom']);
        $this->assertCount(32, $results['kjv']);
        $this->assertEquals(45, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(1,  $results['kjv'][0]->verse);
        $this->assertEquals('Paul, a servant of Jesus Christ, called to be an apostle, separated unto the gospel of God,',  $results['kjv'][0]->text);
        $this->assertEquals(45, $results['kjv'][29]->book);
        $this->assertEquals(1,  $results['kjv'][29]->chapter);
        $this->assertEquals(30, $results['kjv'][29]->verse);
        $this->assertEquals('Backbiters, haters of God, despiteful, proud, boasters, inventors of evil things, disobedient to parents,',  $results['kjv'][29]->text);
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'reference' => 'Rom']);
        $this->assertCount(34, $results['kjv']);
        $this->assertEquals(45, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(5,  $results['kjv'][0]->verse);
        $this->assertEquals('By whom we have received grace and apostleship, for obedience to the faith among all nations, for his name:',  $results['kjv'][0]->text);
        $this->assertEquals(45, $results['kjv'][30]->book);
        $this->assertEquals(14, $results['kjv'][30]->chapter);
        $this->assertEquals(1,  $results['kjv'][30]->verse);
        $this->assertEquals('Him that is weak in the faith receive ye, but not to doubtful disputations.',  $results['kjv'][30]->text);
    }
}
