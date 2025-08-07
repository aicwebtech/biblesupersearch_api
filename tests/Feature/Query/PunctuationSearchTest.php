<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Verses\VerseStandard;
use App\Search;

class PunctuationSearchTest extends TestCase 
{
    protected $base_query = [
        'bible' => 'kjv', 'search' => 'And God said, Let there be light: and there was light.',
    ];

    protected function _initTest($search_type) {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $query = $this->base_query;
        $query['search_type'] = $search_type;

        $Search = new Search($query['search'], ['search_type' => $search_type]);
        $Search->sanitize();

        switch($search_type) {
            case 'regexp':
            case 'boolean':
            case 'phrase':
                $this->assertEquals($query['search'], $Search->search);
                break;
            default:
                $this->assertNotEquals($query['search'], $Search->search);
                //$this->assertEquals('And God said Let there be light and there was light', $Search->search);
        }

        return [$Engine, $query];
    }

    public function testAllWords() 
    {
        list($Engine, $query) = $this->_initTest('all_words');

        $results = $Engine->actionQuery($query);
        $errors = $Engine->getErrors();
        $this->assertCount(1, $results['kjv']);
        $this->assertFalse($Engine->hasErrors());
    }    

    public function testAnyWord() 
    {
        list($Engine, $query) = $this->_initTest('any_word');

        $results = $Engine->actionQuery($query);
        $errors = $Engine->getErrors();
        $this->assertTrue($Engine->hasErrors()); 
        $this->assertCount(1, $errors); // too many results
    }    

    public function testOneWord() 
    {
        list($Engine, $query) = $this->_initTest('one_word');
        $results = $Engine->actionQuery($query);
        $errors = $Engine->getErrors();
        $this->assertTrue($Engine->hasErrors()); 
        $this->assertCount(1, $errors); // too many results
    }    

    public function testTwoOrMoreWords() 
    {
        list($Engine, $query) = $this->_initTest('two_or_more');
        $results = $Engine->actionQuery($query);
        $errors = $Engine->getErrors();
        $this->assertTrue($Engine->hasErrors()); 
        $this->assertCount(1, $errors); // too many results
    }    

    public function testExactPhrase() 
    {
        list($Engine, $query) = $this->_initTest('phrase');
        $results = $Engine->actionQuery($query);
        $errors = $Engine->getErrors();
        $this->assertFalse($Engine->hasErrors());
    }
    
    public function testBoolean() 
    {
        list($Engine, $query) = $this->_initTest('boolean');
        // Dropping the phrase directly in as a boolean search should result in no results, but no query breakage, either
        $results = $Engine->actionQuery($query);
        $errors = $Engine->getErrors();
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);

        // Dropping the phrase into the boolean query as a phrase should return result
        $query['search'] = '"' . $query['search'] . '"';

        $results = $Engine->actionQuery($query);
        $errors = $Engine->getErrors();
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
    }    

    public function testRegexp() 
    {
        list($Engine, $query) = $this->_initTest('regexp');
        $results = $Engine->actionQuery($query);
        $this->assertCount(1, $results['kjv']);
        $this->assertFalse($Engine->hasErrors());
    }
}
