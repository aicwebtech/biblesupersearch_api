<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use App\Engine;
use App\Passage;
use App\Models\Bible;
use App\Models\Feature;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class RequestTest extends TestCase 
{

    /**
     * Request is mapped to 'search' with reference present
     */
    public function testWithReference() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'reference' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results['kjv']);
    }

    /**
     * Request is mapped to 'reference' with search present
     */
    public function testWithSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'request' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results['kjv']);
    }

    /**
     * This will return an error
     */
    public function testWithPassageAndSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'request' => 'Romans', 'reference' => 'Acts', 'page_all' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
    }

    /**
     * 'Romans 1' will be recognized as a reference
     * 'Romans, John' will be recognized as a reference
     */
    public function testAsReference() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans 1', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(32, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans,John', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(83, $results['kjv']);
    }

    public function testWithBooleanProximity() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith PROX(2) hope', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
    }

    /**
     * 'faith' will be recognized as a search
     * 'Romans' will be recognized as a search, not a reference
     */
    public function testAsSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(231, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(6, $results['kjv']); // 7 if module has Pauline postscripts

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Peter John', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(19, $results['kjv']);
    }    

    public function testCrossReferencesAreAggregatedAcrossReturnedVerses(): void
    {
        if(!Feature::isEnabled('cross_references')) {
            $this->markTestSkipped('Cross references feature not installed or enabled');
        }
    
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $singleBibleResults = $Engine->actionQuery([
            'bible' => 'kjv',
            'reference' => 'John 3:16',
            'page_all' => TRUE,
            'cross_references' => TRUE,
        ]);

        $singleBibleMetadata = $Engine->getMetadata();
        $crossReferences = array_values($singleBibleMetadata->cross_references);

        $this->assertFalse($Engine->hasErrors());
        $singleBibleMetadata = $Engine->getMetadata();
        $this->assertIsArray($crossReferences);
        $this->assertNotEmpty($crossReferences, 'Expected cross references to be present in metadata');
        $this->assertArrayHasKey('from_book', $crossReferences[0]);
        $this->assertArrayHasKey('cross_references', $crossReferences[0]);
        $this->assertArrayNotHasKey('created_at', $crossReferences[0]);
        $this->assertArrayNotHasKey('updated_at', $crossReferences[0]);
        $this->assertArrayNotHasKey('created_at', $crossReferences[0]['cross_references'][0]);
        $this->assertArrayNotHasKey('updated_at', $crossReferences[0]['cross_references'][0]);

        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        $multiBibleResults = $Engine->actionQuery([
            'bible' => ['kjv', 'bishops'],
            'reference' => 'John 3:16',
            'page_all' => TRUE,
            'cross_references' => TRUE,
        ]);

        $this->assertFalse($Engine->hasErrors());
        $multiBibleMetadata = $Engine->getMetadata();
        $crossReferences = array_values($multiBibleMetadata->cross_references);

        $this->assertCount(1, $singleBibleResults['kjv']);
        $this->assertCount(2, $multiBibleResults);
        $this->assertCount(count($crossReferences), $crossReferences);
        $this->assertSame(
            [$crossReferences[0]['from_book'], $crossReferences[0]['from_chapter'], $crossReferences[0]['from_verse']],
            [$crossReferences[0]['from_book'], $crossReferences[0]['from_chapter'], $crossReferences[0]['from_verse']]
        );
    }

    // In this case, the request and reference fields are both references.
    // The code will look at the request field and ignore the reference field.
    public function testWithTwoReferences() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Revelation 1:1', 'reference' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);        

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Revelation 1:1', 'reference' => 'Romans 1', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
    }

    public function testWithTwoSearches() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'search' => 'hope', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
    }

    public function testAsRegexpSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'love.{0,200}joy', 'whole_words' => TRUE, 'page_all' => TRUE, 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testDisambiguation() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans']);
        $this->assertFalse($Engine->hasErrors());
        $metadata = $Engine->getMetadata();

        $this->assertCount(1, $metadata->disambiguation);
        $this->assertEquals('Romans', $metadata->disambiguation[0]['simple']);

        // No longer treated as a disambuation situation (BSS-104)
        // $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'kings']);
        // $this->assertFalse($Engine->hasErrors());
        // $metadata = $Engine->getMetadata();

        // $this->assertCount(2, $metadata->disambiguation);
        // $this->assertEquals('1 Kings', $metadata->disambiguation[0]['simple']);
        // $this->assertEquals('2 Kings', $metadata->disambiguation[1]['simple']);

        // This should be a keyword search, with NO disambiguation
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Eve', 'whole_words' => true]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(1, $results['kjv'][0]->book);
        $this->assertEquals(3, $results['kjv'][0]->chapter);
        $this->assertEquals(20, $results['kjv'][0]->verse);

        $metadata = $Engine->getMetadata();
        $this->assertCount(0, $metadata->disambiguation);
    }

    public function testDisambiguationWithPassageLimit() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'mark', 'reference' => 'Revelation', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(8, $results['kjv']);
    }

    public function testNonPassageCharacters() 
    {
        $this->assertFalse( Passage::_containsNonPassageCharacters('Romans 1') );
        $this->assertFalse( Passage::_containsNonPassageCharacters('Romanos') );
        $this->assertFalse( Passage::_containsNonPassageCharacters('Ésaïe 31') );
        $this->assertTrue( Passage::_containsNonPassageCharacters('love.*joy') );
        $this->assertTrue( Passage::_containsNonPassageCharacters('love.{0,200}joy') );
        $this->assertTrue( Passage::_containsNonPassageCharacters('(love OR joy ) hope') );
    }

    public function testSearchGroupedAsPassage() 
    {
        $Engine = Engine::getInstance();

        $query = [
            'bible'         => 'kjv', 
            'request'       => 'faith', 
            'reference'     => 'Ps 89', 
            'whole_words'   => false,
            'data_format'   => 'lite',
        ];

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        // Returns 7 passages, containing 1 verse each
        $this->assertCount(7, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);

        $query['group_passage_search_results'] = true;

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        // Returns 1 passages, containing 7 verses
        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['single_verse']);
        $this->assertEquals(7, $results[0]['verses_count']);
    }    

    public function testSearchGroupedAsPassageMultiDifferentChapters() 
    {
        $Engine = Engine::getInstance();

        $query = [
            'bible'         => 'kjv', 
            'request'       => 'faith', 
            'reference'     => '1 Sam', 
            'whole_words'   => false,
            'data_format'   => 'lite',
        ];

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);

        // Since all of these results are in different chapters, 
        // turning on the passage grouping should have no effect on results

        $query['group_passage_search_results'] = true;

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);
    }    

    public function testSearchGroupedAsPassageMultiSharedChapters() 
    {
        $Engine = Engine::getInstance();

        $query = [
            'bible'         => 'kjv', 
            'request'       => 'faith', 
            'reference'     => 'Romans', 
            'whole_words'   => false,
            'data_format'   => 'lite',
            'page_all'      => true,
        ];

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);

        // Since all of these results are in different chapters, 
        // turning on the passage grouping should have no effect on results

        $query['group_passage_search_results'] = true;

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results);  // 10 different chapters returned
        $this->assertFalse($results[0]['single_verse']);
        $this->assertEquals(4, $results[0]['verses_count']);
    }
}
