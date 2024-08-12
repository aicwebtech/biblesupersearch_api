<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Engine;

class HighlightTest extends TestCase {
    public function testNoHighlight() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for']);
        $tag = config('bss.defaults.highlight_tag');

        foreach($results['kjv'] as $verse) {
            $this->assertTrue((strpos($verse->text, '<' . $tag . '>') === FALSE), 'Should not have highlighting: ' . $verse->text);
        }
    }

    public function testBasic() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE]);
        $tag = config('bss.defaults.highlight_tag');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>be</' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }

    public function testCustomTag() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $tag = 'span';
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE, 'highlight_tag' => $tag]);

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>be</' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }

    public function testWholeWordsWildcard() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $tag = 'em';
        
        $results = $Engine->actionQuery([
            'bible'         => 'kjv', 
            'reference'     => 'Deut', 
            'search'        => 'faith%', 
            'highlight'     => TRUE, 
            'highlight_tag' => $tag, 
            'whole_words'   => TRUE,
        ]);
        
        $this->assertFalse($Engine->hasErrors());
        $verse = $results['kjv'][0];

        $this->assertEquals(5, $verse->book);
        $this->assertEquals(7, $verse->chapter);
        $this->assertEquals(9, $verse->verse);

        // Expected text: 'Know therefore that the LORD thy God, he is God, the <em>faith</em>ful..'
        $this->assertEquals(53, (strpos($verse->text, '<' . $tag . '>')), 'Highlight start tag incorrect: ' . $verse->text);
        $this->assertEquals(62, (strpos($verse->text, '</' . $tag . '>')), 'Highlight end tag incorrect: ' . $verse->text);
    }

    public function testWholeWords() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE, 'whole_words' => TRUE, 'search_type' => 'or']);
        $this->assertFalse($Engine->hasErrors());
        $tag = config('bss.defaults.highlight_tag');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }

        $this->assertTrue((strpos($results['kjv'][2]->text, '<' . $tag . '>be</' . $tag . '>') === FALSE), 'Be should not be highlighted: ' . $verse->text);
    }

    public function testExactCase() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 12', 'search' => 'be for', 'highlight' => TRUE, 'exact_case' => TRUE, 'search_type' => 'or']);
        $this->assertFalse($Engine->hasErrors());
        $tag = config('bss.defaults.highlight_tag');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }

        $this->assertTrue((strpos($results['kjv'][2]->text, '<' . $tag . '>For</' . $tag . '>') === FALSE), 'For should not be highlighted: ' . $verse->text);
    }

    public function testProximity() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'search' => 'cometh by hearing', 'highlight' => TRUE, 'search_type' => 'proximity']);
        $this->assertFalse($Engine->hasErrors());
        $tag = config('bss.defaults.highlight_tag');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }

    public function testExactPhrase() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'search' => 'cometh by hearing', 'highlight' => TRUE, 'search_type' => 'phrase']);
        $this->assertFalse($Engine->hasErrors());
        $tag = config('bss.defaults.highlight_tag');

        foreach($results['kjv'] as $verse) {
            $this->assertFalse((strpos($verse->text, '<' . $tag . '>') === FALSE), 'No highlight: ' . $verse->text);
        }
    }

    public function testKeywordWithinKeyword() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $tag = config('bss.defaults.highlight_tag');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom', 'search' => 'think in', 'highlight' => TRUE, 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $embedded = 'th<' . $tag . '>in</' . $tag . '>k';  

        foreach($results['kjv'] as $key => $verse) {
            $this->assertFalse((strpos($verse->text, $embedded)), 'Highlight tag within tag: ' . $verse->text);
        }
    }    

    // Query error!
    public function testKeywordWithinPhrase() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $tag = config('bss.defaults.highlight_tag');
        
        $embedded = '<' . $tag . '>me</' . $tag . '>asure';  
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '"measure of faith" me', 'highlight' => TRUE, 'whole_words' => FALSE, 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());        

        foreach($results['kjv'] as $key => $verse) {
            $this->assertFalse((strpos($verse->text, $embedded)), 'Highlight tag within tag: ' . $verse->text);
        }
    }

    public function testContextHighlight()
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $tag = config('bss.defaults.highlight_tag');
        $st = '<' . $tag . '>';
        $en = '</' . $tag . '>';

        $results = $Engine->actionQuery([
            'bible'         => 'kjv', 
            'reference'     => 'James 3:7', 
            'highlight'     => true, 
            'context'       => true,
            'context_range' => 5,
        ]);
        
        $this->assertFalse($Engine->hasErrors());        

        foreach($results['kjv'] as $key => $verse) {
            $this->assertEquals(59, $verse->book);
            $this->assertEquals(3, $verse->chapter);

            if($verse->verse == 7) {
                $this->assertStringStartsWith($st, $verse->text);
                $this->assertStringEndsWith($en, $verse->text);
            } else {
                $this->assertStringNotContainsString($st, $verse->text);
                $this->assertStringNotContainsString($en, $verse->text);
            }
        }
    }

    public function testAmpersandIssue()
    {
        if(!\App\Models\Bible::isEnabled('geneva')) {
            $this->markTestSkipped();
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery([
            'bible'         => 'geneva', 
            'reference'     => 'Exodus', 
            'search'        => 'coast',
            'highlight'     => true, 
            'whole_words'   => false,
        ]);

        $this->assertFalse($Engine->hasErrors());   

        // Make sure & is still in text after highlighing ...
        $this->assertStringContainsString('&', $results['geneva'][1]->text);
    }
}
