<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class AdvancedSearchTest extends TestCase {
    public function testAllWords() {
        $Engine = new Engine();
        $input = ['bible' => 'kjv', 'search_all' => 'faith hope', 'format_structure' => 'raw'];
        
        $results = $Engine->actionQuery($input);
        $this->assertCount(9, $results['kjv']);
        
        $input = ['bible' => 'kjv', 'search_all' => 'faith hope', 'reference' => '1 Thess', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertCount(2, $results['kjv']);
    }
    
    public function testAnyWords() {
        $Engine = new Engine();
        $input = ['bible' => 'kjv', 'search_any' => 'faith hope', 'format_structure' => 'raw'];
        
        $results = $Engine->actionQuery($input);
        $this->assertCount(462, $results['kjv']);
        
        $input['whole_words'] = 'on';
        $results = $Engine->actionQuery($input);
        $this->assertCount(344, $results['kjv']);
        
        $input = ['bible' => 'kjv', 'search_any' => 'faith hope', 'reference' => 'Acts', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertCount(24, $results['kjv']);
    }
    
    public function testOneWord() {
        $Engine = new Engine();
        $input = ['bible' => 'kjv', 'search_one' => 'faith hope', 'format_structure' => 'raw'];
        
        $results = $Engine->actionQuery($input);
        $this->assertCount(453, $results['kjv']);
        
        $input['whole_words'] = 'on';
        
        $results = $Engine->actionQuery($input);
        $this->assertCount(336, $results['kjv']);
        
        $input = ['bible' => 'kjv', 'search_one' => 'faith hope', 'reference' => 'Acts', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertCount(24, $results['kjv']);
    }
    
    public function testExactPhrase() {
        $Engine = new Engine();
        $input = ['bible' => 'kjv', 'search_phrase' => 'free spirit', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertCount(1, $results['kjv']);
        
        $input = ['bible' => 'kjv', 'search_phrase' => 'Lord of Hosts', 'format_structure' => 'raw'];
        $results = $Engine->actionQuery($input);
        $this->assertCount(235, $results['kjv']);
        
        $input['whole_words'] = 'yes';
        $results = $Engine->actionQuery($input);
        $this->assertCount(235, $results['kjv']);
    }
    
    public function testNoneWords() {
        $Engine = new Engine();
        $input = ['bible' => 'kjv', 'search_none' => 'faith hope', 'reference' => 'Rom', 'format_structure' => 'raw'];
        
        $results = $Engine->actionQuery($input);
        $this->assertCount(432, $results['kjv']);
    }
}
