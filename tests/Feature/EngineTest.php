<?php

namespace Tests\Feature;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Engine;
use App\Models\Bible;

class EngineTest extends TestCase
{
    public function testInstance() 
    {
        $engine = new Engine();
        $this->assertInstanceOf('App\Engine', $engine);
    }

    /**
     * Tests adding the default Bible on instantiation
     */
    public function testConfig() 
    {
        $engine = new Engine();
        $Bibles = $engine->getBibles();
        $this->assertCount(1, $Bibles);
        $this->assertContainsOnlyInstancesOf('App\Models\Bible', $Bibles);
    }

    public function testMethodAddBible() 
    {
        $engine = new Engine();
        $engine->addBible('kjv');
        $this->assertFalse($engine->hasErrors());
        $Bibles = $engine->getBibles();
        $this->assertInstanceOf('App\Models\Bible', $Bibles['kjv']);
    }

    public function testMethodSetBibles() 
    {
        $bibles = ['kjv', 'tr', 'tyndale', 'luther'];

        foreach($bibles as $key => $bible) {
            if(!Bible::isEnabled($bible)) {
                unset($bibles[$key]);
            }
        }

        if(empty($bibles)) {
            return;
        }

        $engine = new Engine();
        $engine->setBibles($bibles);
        $this->assertFalse($engine->hasErrors());
        $Bibles = $engine->getBibles();
        $this->assertCount(count($bibles), $Bibles);
    }

    public function testOtherBibles() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv_strongs', 'search' => 'faith']);

        $modules = ['kjv_strongs', 'tyndale', 'bishops', 'coverdale'];

        foreach($modules as $module) {
            $Bible = Bible::findByModule($module);

            if(!$Bible->enabled) {
                continue;
            }

            $results = $Engine->actionQuery(['bible' => $module, 'search' => 'faith']);
            $this->assertFalse($Engine->hasErrors(), 'failed search on module: ' . $module);
            $this->assertTrue(count($results[$module]) > 0, 'empty results on module:' . $module);

            $results = $Engine->actionQuery(['bible' => $module, 'reference' => 'Rom']);
            $this->assertFalse($Engine->hasErrors(), 'failed lookup on module: ' . $module);
            $this->assertTrue(count($results[$module]) > 0, 'empty results on module:' . $module);
        }

        $this->assertTrue(TRUE);
    }

    public function testBasicSearch() 
    {
        // NOT whole word searches!
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith']);
        $this->assertCount(338, $results['kjv']);
        $this->assertEquals(4,  $results['kjv'][0]->book);
        $this->assertEquals(12, $results['kjv'][0]->chapter);
        $this->assertEquals(7,  $results['kjv'][0]->verse);
        $this->assertEquals('My servant Moses is not so, who is faithful in all mine house.',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(51, $results['kjv'][201]->book);
        $this->assertEquals(1,  $results['kjv'][201]->chapter);
        $this->assertEquals(4,  $results['kjv'][201]->verse);
        $this->assertEquals('Since we heard of your faith in Christ Jesus, and of the love which ye have to all the saints,',  $this->_tp($results['kjv'][201]->text));

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom']);
        $this->assertCount(32, $results['kjv']);
        $this->assertEquals(45, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(1,  $results['kjv'][0]->verse);
        $this->assertEquals('Paul, a servant of Jesus Christ, called to be an apostle, separated unto the gospel of God,',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(45, $results['kjv'][29]->book);
        $this->assertEquals(1,  $results['kjv'][29]->chapter);
        $this->assertEquals(30, $results['kjv'][29]->verse);
        $this->assertEquals('Backbiters, haters of God, despiteful, proud, boasters, inventors of evil things, disobedient to parents,',  $this->_tp($results['kjv'][29]->text));

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'reference' => 'Rom']);
        $this->assertCount(34, $results['kjv']);
        $this->assertEquals(45, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(5,  $results['kjv'][0]->verse);
        $this->assertEquals('By whom we have received grace and apostleship, for obedience to the faith among all nations, for his name:',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(45, $results['kjv'][30]->book);
        $this->assertEquals(14, $results['kjv'][30]->chapter);
        $this->assertEquals(1,  $results['kjv'][30]->verse);
        $this->assertEquals('Him that is weak in the faith receive ye, but not to doubtful disputations.',  $this->_tp($results['kjv'][30]->text));
    }

    public function testWholeWordSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'exact_case' => FALSE]);
        $this->assertCount(231, $results['kjv']);
        $this->assertEquals(5,  $results['kjv'][0]->book);
        $this->assertEquals(32, $results['kjv'][0]->chapter);
        $this->assertEquals(20, $results['kjv'][0]->verse);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy', 'whole_words' => 'yes']);
        $this->assertCount(5, $results['kjv']);
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy', 'whole_words' => 'yes', 'search_type' => 'or']);
        $this->assertCount(381, $results['kjv']);
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith && joy || "free spirit"', 'whole_words' => 'yes', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        
        // Expected value of 6 is CORRECT.
        // Search faith joy (All words, whole words checked) => 5 results
        // Search free spirit (Exact Phrase) => 1 result
        $this->assertCount(6, $results['kjv']);
        $this->assertEquals(19,  $results['kjv'][0]->book);
        $this->assertEquals(51, $results['kjv'][0]->chapter);
        $this->assertEquals(12, $results['kjv'][0]->verse);
        $this->assertEquals('Restore unto me the joy of thy salvation; and uphold me with thy free spirit.', $this->_tp($results['kjv'][0]->text));
    }

    public function testBookRangeSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'reference' => 'Matt - John', 'whole_words' => TRUE]);
        $this->assertCount(29, $results['kjv']);
        $this->assertEquals(40, $results['kjv'][0]->book);
        $this->assertEquals(6,  $results['kjv'][0]->chapter);
        $this->assertEquals(30, $results['kjv'][0]->verse);
    }

    public function testProximitySearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'reference' => 'Rom', 'search_type' => 'proximity']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(13, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'search_type' => 'chapter']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(151, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'reference' => 'Rom', 'search_type' => 'book']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(43, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROC(5) hope', 'reference' => 'Rom', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['kjv']);
    }

    public function testAPIBooks() 
    {
        $Engine = new Engine();
        $Books = $Engine->actionBooks(array('language' => 'en'));
        $this->assertCount(66, $Books);
    }

    public function testSingleton() 
    {
        $Engine = Engine::getInstance();
        $this->assertInstanceOf('App\Engine', $Engine);

        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith']);
        $this->assertCount(338, $results['kjv']);
        $this->assertEquals(4,  $results['kjv'][0]->book);
        $this->assertEquals(12, $results['kjv'][0]->chapter);
        $this->assertEquals(7,  $results['kjv'][0]->verse);
        $this->assertEquals('My servant Moses is not so, who is faithful in all mine house.',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(51, $results['kjv'][201]->book);
        $this->assertEquals(1,  $results['kjv'][201]->chapter);
        $this->assertEquals(4,  $results['kjv'][201]->verse);
        $this->assertEquals('Since we heard of your faith in Christ Jesus, and of the love which ye have to all the saints,',  $this->_tp($results['kjv'][201]->text));
    }

    private function _tp($text) 
    {
        return trim($text, '¶ ');
    }
}
