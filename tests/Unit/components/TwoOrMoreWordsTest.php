<?php

//namespace Tests\Unit\components;

//use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use aicwebtech\BibleSuperSearch\SqlSearch;

class TwoOrMoreWordsTest extends TestCase {

    public function testFourOne() {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 1);
        $this->assertEquals(implode(' OR ', $keywords), $query);
    }

    public function testFourTwo() {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 2);
        $expected = 't1 AND t2 OR t1 AND t3 OR t1 AND t4 OR t2 AND t3 OR t2 AND t4 OR t3 AND t4';
        $this->assertEquals($expected, $query);
    }

    public function testFourThree() {
        $keywords = $this->_generateKeywords(4);
        $expected = 't1 AND t2 AND t3 OR t1 AND t2 AND t4 OR t1 AND t3 AND t4 OR t2 AND t3 AND t4';
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 3);
        $this->assertEquals($expected, $query);
    }

    public function testFourFour() {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 4);
        $this->assertEquals(implode(' AND ', $keywords), $query);
    }

    public function testFourFive() {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 5);
        $this->assertEquals(implode(' AND ', $keywords), $query);
    }

    public function testFiveThree() {
        $keywords = $this->_generateKeywords(5);
        $expected = 't1 AND t2 AND t3 OR t1 AND t2 AND t4 OR t1 AND t2 AND t5 OR t1 AND t3 AND t4 OR t1 AND t3 AND t5 OR '
                . 't1 AND t4 AND t5 OR t2 AND t3 AND t4 OR t2 AND t3 AND t5 OR t2 AND t4 AND t5 OR t3 AND t4 AND t5';

        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 3);
        $this->assertEquals($expected, $query);
    }

    public function testSixTwo() {
        $keywords = $this->_generateKeywords(6);
        $expected = 't1 AND t2 OR t1 AND t3 OR t1 AND t4 OR t1 AND t5 OR t1 AND t6 OR t2 AND t3 OR t2 AND t4 OR t2 AND t5 OR t2 AND t6'
                . ' OR t3 AND t4 OR t3 AND t5 OR t3 AND t6 OR t4 AND t5 OR t4 AND t6 OR t5 AND t6';

        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 2);
        $this->assertEquals($expected, $query);
    }

    private function _generateKeywords($count = 2) {
        $keywords = [];

        for($i = 1; $i <= $count; $i++) {
            $keywords[] = 't' . $i;
        }

        return $keywords;
    }
}
