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
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith%', 'whole_words' => TRUE]);
        $this->assertCount(336, $results['kjv']);
    }
    
    public function testWithPhrase() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith && joy || "free spirit"']);
        //$this->assertCount(9, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => "faith && joy || 'free spirit'"]);
        //$this->assertCount(9, $results['kjv']);
       
    }
 }
