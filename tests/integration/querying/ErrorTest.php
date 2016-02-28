<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class ErrorTest extends TestCase {
    public function testNoResults() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'bacon']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.no_results'), $errors[0]);
    }
    
    public function testBibleNoResults() {
        $Engine = new Engine();
        // Neither Textus Receptus nor the Tyndale Bible have Isaiah
        $results = $Engine->actionQuery(['bible' => array('kjv', 'tr', 'tyndale'), 'reference' => 'Isaiah 1:1']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(2, $errors);
        $this->assertEquals( trans('errors.bible_no_results', ['module' => 'tr']), $errors[0]);
        $this->assertEquals( trans('errors.bible_no_results', ['module' => 'tyndale']), $errors[1]);
        $this->assertCount(1, $results['kjv']);
    }
    
    public function testFalseBible() {
        $engine = new Engine();
        $engine->addBible('aaa'); // Fictitious Bible module AAA
        $this->assertTrue($engine->hasErrors());
        $errors = $engine->getErrors();
        $this->assertEquals("Bible text 'aaa' not found.", $errors[0]);
    }
    
}
