<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class KeywordTest extends TestCase
{
    public function testRepeatedKeyword() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy joy love joy', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        //$errors = $Engine->getErrors();
        //$this->assertCount(1, $errors);
        //$this->assertEquals( trans('errors.no_results'), $errors[0]);;
    }

    public function testWildcard() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith%', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertCount(336, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'world tempt%', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(3,  $results['kjv'][0]->chapter);
        $this->assertEquals(10, $results['kjv'][0]->verse);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'tempt% world ', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
        $this->assertEquals(3,  $results['kjv'][0]->chapter);
        $this->assertEquals(10, $results['kjv'][0]->verse);
    }

    public function testWithPhrase() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith && joy || "free spirit"']);
        //$this->assertCount(9, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => "faith && joy || 'free spirit'"]);
        //$this->assertCount(9, $results['kjv']);

    }

    public function testExactPhraseOneWord() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'search_type' => 'phrase', 'page_all' => TRUE, 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(338, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'search_type' => 'phrase', 'page_all' => TRUE, 'whole_words' => 'false']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(338, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'search_type' => 'phrase', 'page_all' => TRUE, 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(231, $results['kjv']);
    }

    public function testExactPhraseWildcard() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith%', 'search_type' => 'phrase', 'page_all' => TRUE, 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(336, $results['kjv']);
    }

    public function testExactPhraseApostrophe() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'Jacob\'s Trouble', 'search_type' => 'phrase', 'page_all' => TRUE, 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(24, $results['kjv'][0]->book);
        $this->assertEquals(30, $results['kjv'][0]->chapter);
        $this->assertEquals(7,  $results['kjv'][0]->verse);
    }

    public function testTwoOrMore() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope love', 'search_type' => 'two_or_more', 'page_all' => TRUE, 'whole_words' => TRUE]);
        $this->assertCount(23, $results['kjv']);
        $this->assertEquals(45, $results['kjv'][0]->book);
        $this->assertEquals(5, $results['kjv'][0]->chapter);
        $this->assertEquals(2, $results['kjv'][0]->verse);
    }
 }
