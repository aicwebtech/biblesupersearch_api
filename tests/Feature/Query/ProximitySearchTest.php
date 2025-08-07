<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Verses\VerseStandard;

class ProximitySearchTest extends TestCase 
{
    public function testParenthensesMismatch() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '(faith PROX(2) joy joy love joy', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.prox_paren_mismatch'), $errors[0]);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '(escape hide PROX(5) wrath indignation)', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.prox_paren_mismatch'), $errors[0]);
    }

    public function testNotBoolean() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROX(2) joy', 'search_type' => 'or']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.prox_operator_not_allowed'), $errors[0]);
    }

    public function testMissingKeywordChap() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith CHAP', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'CHAP']), $errors[0]);    

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'CHAP God', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        // print_r($errors);
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_beginning', ['op' => 'CHAP']), $errors[0]);
    }    

    public function testMissingKeywordProx() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROX(5)', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'PROX(5)']), $errors[0]);       

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'PROX(5) God', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_beginning', ['op' => 'PROX(5)']), $errors[0]);
    }    

    public function testMissingKeywordProc() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROC(5)', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'PROC(5)']), $errors[0]);  

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'PROC(5) God', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_beginning', ['op' => 'PROC(5)']), $errors[0]);
    }    

    public function testMissingKeywordBook() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith BOOK', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_end', ['op' => 'BOOK']), $errors[0]);  

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'BOOK God', 'search_type' => 'boolean']);

        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.operator.op_at_beginning', ['op' => 'BOOK']), $errors[0]);
    }

    public function testProximitySearchType() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy love', 'search_type' => 'proximity', 'proximity_limit' => 10, 'whold_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());

        $query = "
            SELECT bible_1.id AS id_1, bible_2.id AS id_2, bible_3.id AS id_3

            FROM bss_verses_kjv AS bible_1
            INNER JOIN bss_verses_kjv AS bible_2 ON bible_2.book = bible_1.book
            AND bible_2.id BETWEEN bible_1.id - 10 AND bible_1.id + 10
            AND (bible_2.book != 19 OR bible_2.chapter = bible_1.chapter)
            AND (
                    `bible_2`.`text` LIKE '%joy%'
            )
            INNER JOIN bss_verses_kjv AS bible_3 ON bible_3.book = bible_2.book
            AND bible_3.id BETWEEN bible_2.id - 10 AND bible_2.id + 10
            AND (bible_3.book != 19 OR bible_3.chapter = bible_2.chapter)
            AND (
                    `bible_3`.`text` LIKE '%love%'
            )
            WHERE
                    (
                            `bible_1`.`text` LIKE '%faith%'
                    )
        ";

        // $test_count = VerseStandard::proximityQueryTest($query);  // returns 96
        $this->assertCount(96, $results['kjv']);
    }

    public function testMixedProxLimits() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROX(2) joy PROX(5) love;', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());

        $query = "
            SELECT bible_1.id AS id_1, bible_2.id AS id_2, bible_3.id AS id_3
            FROM
                    bss_verses_kjv AS bible_1
            INNER JOIN bss_verses_kjv AS bible_2 ON bible_2.book = bible_1.book
            AND bible_2.id BETWEEN bible_1.id - 2
            AND bible_1.id + 2
            AND (
                    `bible_2`.`text` LIKE '%joy%'
            )
            INNER JOIN bss_verses_kjv AS bible_3 ON bible_3.book = bible_2.book
            AND bible_3.id BETWEEN bible_2.id - 5
            AND bible_2.id + 5
            AND (
                    `bible_3`.`text` LIKE '%love%'
            )
            WHERE
            (
                    `bible_1`.`text` LIKE '%faith%'
            )";

        //$test_count = VerseStandard::proximityQueryTest($query);  // returns 37
        $this->assertCount(37, $results['kjv']);

        // Same test, but using request field
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith PROX(2) joy PROX(5) love;', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(37, $results['kjv']);
    }

    public function testProximitySearchTypeDefaultLimit() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        // Default proximity limit is 5 (hardcoded)
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'search_type' => 'proximity', 'reference' => 'Rom 4']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(6, $results['kjv']);
    }

    public function testChapterSearchType() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'search_type' => 'chapter', 'reference' => 'Rom 4']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['kjv']);
    }

    public function testBookSearchType() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        // This essentially asks, which book(s) contain both 'hall' and 'hallowed'?
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hall hallowed', 'search_type' => 'book', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(4, $results['kjv']);
    }

    public function testAPI118() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '(refuge) PROX(5) (try | tempt)', 'search_type' => 'boolean', 'reference' => 'Prophets; NT;', 'whole_words' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
    }

    public function testAPI117() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'escape | hide PROX(5) wrath | indignation', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertLessThan(100, count($results['kjv'])); // Return count not vetted to the number
    }

    public function testProxOpEnd() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'AND ( book hope) PROX(4) faith', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '( book hope) PROX(4) faith AND', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());        

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'OR ( book hope) PROX(4) faith', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '( book hope) PROX(4) faith OR', 'search_type' => 'boolean']);
        $this->assertTrue($Engine->hasErrors());
    }
 
    // Make sure that non-proximity search types don't attempt to send proximity terms to the SQL query
    public function testNonBooleanProximitySearchTypes() 
    {
        $Engine = Engine::getInstance();
        $params = ['bible' => 'kjv', 'search' => 'escape | hide PROX(5) wrath | indignation', 'search_type' => NULL];

        foreach(config('bss.search_types') as $st) {
            if($st['bool']) {
                continue;
            }

            $params['search_type'] = $st['value'];
            $msg = 'Should NOT be able to send boolean or proximity operators for search type of ' . $st['value'];
            $results = $Engine->actionQuery($params);
            $this->assertTrue($Engine->hasErrors(), $msg);            
            $results = $Engine->actionQuery($params);
            $this->assertTrue($Engine->hasErrors(), $msg);
        }
    }    

    public function testBooleanProximitySearchTypes() 
    {
        $Engine = Engine::getInstance();
        $params = ['bible' => 'kjv', 'search' => 'escape | hide PROX(5) wrath | indignation', 'search_type' => NULL];

        foreach(config('bss.search_types') as $st) {
            if(!$st['bool']) {
                continue;
            }

            $params['search_type'] = $st['value'];
            $msg = 'Should be able to send boolean or proximity operators for search type of ' . $st['value'];
            $results = $Engine->actionQuery($params);
            $this->assertFalse($Engine->hasErrors(), $msg);            
            $results = $Engine->actionQuery($params);
            $this->assertFalse($Engine->hasErrors(), $msg);
        }
    }

}
