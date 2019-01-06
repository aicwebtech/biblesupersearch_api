<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Verses\VerseStandard;

class ProximitySearchTest extends TestCase {
    public function testParenthensesMismatch() {
        $Engine = new Engine();
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

    public function testNotBoolean() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROX(2) joy', 'search_type' => 'any']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals( trans('errors.prox_operator_not_allowed'), $errors[0]);
    }

    public function _testWordsWithinNumberVerses() {
        // ?? not sure what this was supposed to test
    }

    public function testProximitySearchType() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy love', 'search_type' => 'proximity', 'proximity_limit' => 10, 'whold_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());

        $query = "
            SELECT bible_1.id AS id_1, bible_2.id AS id_2, bible_3.id AS id_3

            FROM bss_verses_kjv AS bible_1
            INNER JOIN bss_verses_kjv AS bible_2 ON bible_2.book = bible_1.book
            AND bible_2.id BETWEEN bible_1.id - 10
            AND bible_1.id + 10
            AND (
                    `bible_2`.`text` LIKE '%joy%'
            )
            INNER JOIN bss_verses_kjv AS bible_3 ON bible_3.book = bible_2.book
            AND bible_3.id BETWEEN bible_2.id - 10
            AND bible_2.id + 10
            AND (
                    `bible_3`.`text` LIKE '%love%'
            )
            WHERE
                    (
                            `bible_1`.`text` LIKE '%faith%'
                    )
        ";

        //$test_count = VerseStandard::proximityQueryTest($query);  // returns 99
        $this->assertCount(99, $results['kjv']);
    }

    public function testMixedProxLimits() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROX(2) joy PROX(5) love', 'search_type' => 'boolean']);
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
    }

    public function testProximitySearchTypeDefaultLimit() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        // Default proximity limit is 5 (hardcoded)
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'search_type' => 'proximity', 'reference' => 'Rom 4']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(6, $results['kjv']);
    }

    public function testChapterSearchType() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'search_type' => 'chapter', 'reference' => 'Rom 4']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['kjv']);
    }

    public function testBookSearchType() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        // This essentially asks, which book(s) contain both 'hall' and 'hallowed'?
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hall hallowed', 'search_type' => 'book', 'whole_words' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(4, $results['kjv']);
    }

    public function testAPI118() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => '(refuge) PROX(5) (try | tempt)', 'search_type' => 'boolean', 'reference' => 'Prophets; NT;', 'whole_words' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
    }

    public function testAPI117() {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'escape | hide PROX(5) wrath | indignation', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertLessThan(100, count($results['kjv'])); // Return count not vetted to the number
    }

    public function _testQueryBinding() {
        // Non-essential test
        return;

        // Cannot reuse named bindings with PDO extension?
        // This is special :P
        $binddata = array('kjv','kjv');

        $Bibles = DB::table('bibles')->whereRaw('module = ? OR module_v2 = ?', $binddata)->get();
        $this->assertCount(1, $Bibles);
        $this->assertEquals('kjv', $Bibles[0]->module);

        $binddata = array(
            ':bible'  => 'kjv',
            ':bible2' => 'kjv',
        );

        $Bibles = DB::table('bibles')->whereRaw('module = :bible OR module_v2 = :bible2', $binddata)->get();
        $this->assertCount(1, $Bibles);
        $this->assertEquals('kjv', $Bibles[0]->module);

        $binddata = array(
            ':bible'  => 'kjv',
        );

        //$Bibles = DB::table('bibles')->whereRaw('module = :bible OR module_v2 = :bible', $binddata)->get();
        //$this->assertCount(1, $Bibles);
        //$this->assertEquals('kjv', $Bibles[0]->module);
    }

}
