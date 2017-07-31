<?php

//namespace Tests\Feature\integration\querying;

//use Tests\TestCase;
use App\Engine;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RequestTest extends TestCase {

    /**
     * Request is mapped to 'search' with reference present
     */
    public function testWithReference() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'reference' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results['kjv']);
    }

    /**
     * Request is mapped to 'reference' with search present
     */
    public function testWithSearch() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'request' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results['kjv']);
    }

    /**
     * This will return an error
     */
    public function testWithPassageAndSearch() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'request' => 'Romans', 'reference' => 'Acts', 'page_all' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
    }

    /**
     * 'Romans 1' will be recognized as a reference
     * 'Romans, John' will be recognized as a reference
     */
    public function testAsReference() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans 1', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(32, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans,John', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(83, $results['kjv']);
    }

    /**
     * 'faith' will be recognized as a search
     * 'Romans' will be recognized as a search, not a reference
     */
    public function testAsSearch() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'whole_words' => TRUE, 'page_all' => TRUE]);
        //print_r($Engine->getErrors());
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(231, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(6, $results['kjv']);

//        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Peter John', 'whole_words' => FALSE, 'page_all' => TRUE]);
//        $this->assertFalse($Engine->hasErrors());
//        $this->assertCount(6, $results['kjv']);
    }
}
