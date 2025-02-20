<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Bible;

class ErrorTest extends TestCase 
{
    public function testNoQuery() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery([]);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.no_query'), $errors[0]);
    }

    public function testNoResults() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'bacon']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.no_results'), $errors[0]);
    }    

    public function testNoKeywords() 
    {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.no_query'), $errors[0]);

        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '   ']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.no_query'), $errors[0]);
    }

    public function testIllegalCharacters() {
        $list = ['*', ' * ', ' + ',  ' " "'];
        $list[] = '"' . chr(32) . '"';

        $Engine = Engine::getInstance();
        
        foreach($list as $qu) {        
            $qu_tr = $qu;
            $qu_tr = $qu_tr ?: $qu;

            // Reference
            $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => $qu]);
            $this->assertTrue($Engine->hasErrors());
            $errors = $Engine->getErrors();
            $this->assertCount(1, $errors);
            $this->assertEquals( trans('errors.passage_not_found', ['passage' => $qu]), $errors[0]);      

            // Search, type = all words
            $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => $qu]);
            $this->assertTrue($Engine->hasErrors(), $qu);
            $errors = $Engine->getErrors();

            $this->assertCount(1, $errors);
            $this->assertEquals( trans('errors.invalid_search.general', ['search' => $qu_tr]), $errors[0]);

            $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => $qu]);
            $this->assertTrue($Engine->hasErrors());
            $errors = $Engine->getErrors();
            $this->assertCount(1, $errors);
            $this->assertEquals( trans('errors.invalid_search.general', ['search' => $qu_tr]), $errors[0]);            
            
            // Search, type = boolean
            $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => $qu, 'search_type' => 'boolean']);
            $this->assertTrue($Engine->hasErrors());
            $errors = $Engine->getErrors();

            $this->assertCount(1, $errors);
            $this->assertEquals( trans('errors.invalid_search.general', ['search' => $qu_tr]), $errors[0]);

            $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => $qu, 'search_type' => 'boolean']);
            $this->assertTrue($Engine->hasErrors());
            $errors = $Engine->getErrors();
            $this->assertCount(1, $errors);
            $this->assertEquals( trans('errors.invalid_search.general', ['search' => $qu_tr]), $errors[0]);
        }
    }

    public function testParallelLookupNoResults() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        if(!Bible::isEnabled('tr') || !Bible::isEnabled('tyndale')) {
            $this->markTestSkipped('Bible tr or Bible tyndale not installed or enabled');
        }

        // Neither Textus Receptus nor the Tyndale Bible have Isaiah
        $results = $Engine->actionQuery(['bible' => array('kjv', 'tr', 'tyndale'), 'reference' => 'Isaiah 1:1']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(2, $errors);
        $this->assertEquals( trans('errors.bible_no_results', ['module' => 'Textus Receptus NT']), $errors[0]);
        $this->assertEquals( trans('errors.bible_no_results', ['module' => 'Tyndale Bible']), $errors[1]);
        $this->assertCount(1, $results['kjv']);
        $this->assertArrayNotHasKey('tr', $results);
        $this->assertArrayNotHasKey('tyndale', $results);
    }    

    public function testParallelSearchNoResults() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        if(!Bible::isEnabled('web')) {
            $this->markTestSkipped('Bible web not installed or enabled');
        }

        $results = $Engine->actionQuery(['bible' => array('kjv', 'web'), 'search' => 'cometh']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.parallel_bible_no_results', ['module' => 'World English Bible']), $errors[0]);
        $this->assertNotEmpty($results['kjv']);
        $this->assertNotEmpty($results['web']); // Back-populated results that don't include the keyword.
    }    

    public function testParallelSearchNoResultsSuppressed() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        if(!Bible::isEnabled('web')) {
            $this->markTestSkipped('Bible web not installed or enabled');
        }

        $results = $Engine->actionQuery([
            'bible' => array('kjv', 'web'), 
            'search' => 'cometh',
            'parallel_search_error_suppress' => true
        ]);
        
        $this->assertFalse($Engine->hasErrors());
        $this->assertNotEmpty($results['kjv']);
        $this->assertNotEmpty($results['web']); // Back-populated results that don't include the keyword.
    }

    public function testFalseBible() 
    {
        $Engine = new Engine();
        $Engine->addBible('aaaa_9876'); // Fictitious Bible module
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals("Bible text not found: 'aaaa_9876'", $errors[0]);
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

    public function testBookNumber() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => '19 91:2-8']);
        $this->assertTrue($Engine->hasErrors());
    }

    public function testUnfoundVerses() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 4:29-39 ']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        // This does NOT return a search no results error, as that is only for searches and not passage requests
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.passage_not_found', ['passage' => 'Rom 4:29-39']), $errors[0]);
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

        // This is NOT an error - Romans is a valid keyword
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'Romans', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testInvalidBook() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Actz', 'search' => 'faith']);
        $this->assertTrue($Engine->hasErrors());
        $this->assertEquals(4, $Engine->getErrorLevel());

        // Two errorenous books
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Actz; Romaans', 'search' => 'faith']);
        $this->assertTrue($Engine->hasErrors());
        $this->assertEquals(4, $Engine->getErrorLevel());

        // One good, one bad
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Actz; Romans', 'search' => 'faith']);
        $this->assertTrue($Engine->hasErrors());
    }


    public function testInvalidCharacters() {
        $Engine = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Acts', 'search' => '@']);
        $this->assertTrue($Engine->hasErrors());
        $this->assertEquals(4, $Engine->getErrorLevel());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.invalid_search.general', ['search' => '@']), $errors[0]);
    }

    /**
     * Test attempting to look up a verse that does not exist
     */
    public function testAbsentVerseLookup() {
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Jn 3:96', 'data_format' => 'passage']);
        $this->assertTrue($Engine->hasErrors());

        // It should find the valid verse
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 5:15; Jn 3:96', 'data_format' => 'passage']);
        $this->assertTrue($Engine->hasErrors()); // Yes, it has errors.
        //$this->assertCount(2, $results);
        //$this->assertEquals(1, $results[0]['verses_count']);
        //$this->assertEquals(0, $results[1]['verses_count']);
        // Empty passages are now removed from results
        $this->assertCount(1, $results);
        $this->assertEquals(1, $results[0]['verses_count']);
        $this->assertArrayNotHasKey(1, $results);
    }

    public function testGlobalMaximumResults() {
        $maximum = config('bss.global_maximum_results');
        $msg     = trans('errors.result_limit_reached', ['maximum' => config('bss.global_maximum_results')]);
        $Engine  = new Engine();
        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Psalms 1 -', 'data_format' => 'raw']);
        $errors  = $Engine->getErrors();
        $this->assertTrue($Engine->hasErrors());
        $this->assertCount($maximum, $results['kjv']);
        $this->assertEquals($msg, $errors[0]);
    }

    public function testBooleanMisplacedOperators() {
        $Engine  = new Engine();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '&& faith']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.operator.op_at_beginning', ['op' => 'AND']), $errors[0]);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith &']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'AND']), $errors[0]);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'OR faith']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.operator.op_at_beginning', ['op' => 'OR']), $errors[0]);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith |']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'OR']), $errors[0]);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'XOR faith']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.operator.op_at_beginning', ['op' => 'XOR']), $errors[0]);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith ^']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'XOR']), $errors[0]);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom 1', 'search' => 'NOT faith']);
        $this->assertFalse($Engine->hasErrors()); // This is NOT an error
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith !']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'NOT']), $errors[0]);
    }
}
