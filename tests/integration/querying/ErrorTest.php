<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class ErrorTest extends TestCase {
    public function testNoQuery() {
        $Engine = new Engine();
        $results = $Engine->actionQuery([]);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.no_query'), $errors[0]);
    }

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
        $Engine->setDefaultDataType('raw');
        
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
        $Engine = new Engine();
        $Engine->addBible('aaaa_9876'); // Fictitious Bible module
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals("Bible text 'aaaa_9876' not found.", $errors[0]);
    }
    
    public function testPassageInvalidReference() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '  Habrews 4:8; 1 Tom 3:1-5, 9 ']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(2, $errors);
        $this->assertEquals(trans('errors.book.not_found', ['book' => 'Habrews']), $errors[0]);
        $this->assertEquals(trans('errors.book.not_found', ['book' => '1 Tom']), $errors[1]);
    }
    
    public function testPassageInvalidRangeReference() {
        $Engine = new Engine();
        $reference = 'Ramans - Revelation';
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => $reference, 'search' => 'faith']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.book.invalid_in_range', ['range' => $reference]), $errors[0]);
    }
    
    public function testPassageRangeReferenceNoSearch() {
        $Engine = new Engine();
        $reference = 'Romans - Revelation';
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => $reference,]);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.book.multiple_without_search'), $errors[0]);
    }
    
    public function testParenthensesMismatch() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '(faith (joy love) hope', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.paren_mismatch'), $errors[0]);
    }
    
    public function testSwappedParameter() {
        $Engine = new Engine();
        // User accidentially places search in reference input
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'faith', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        //$this->assertEquals( trans('errors.no_results'), $errors[0]);
        
        // User accidentially places reference in search input
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '1 Jn 5:7, 9, 45', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.invalid_search.reference', ['search' => '1 Jn 5:7, 9, 45']), $errors[0]);
    }
    
}
