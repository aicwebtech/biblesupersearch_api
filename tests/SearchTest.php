<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Search;

class SearchTest extends TestCase {
    
    public function testEmptySearch() {
        $empty = array('', NULL, FALSE, array());
        
        foreach($empty as $val) {            
            $Search = Search::parseSearch($val);
            $this->assertFalse($Search);
        }
    }
    
    public function testMethodIsSpecial() {
        $this->assertFalse( Search::isSpecial('faith hope charity','and') );
        $this->assertFalse( Search::isSpecial('faith hope charity','or') );
        $this->assertFalse( Search::isSpecial('faith hope charity','phrase') );
        $this->assertFalse( Search::isSpecial('faith hope charity','regexp') );
        $this->assertFalse( Search::isSpecial('faith hope charity','boolean') );
        $this->assertFalse( Search::isSpecial('faith hope charity','strongs') ); // This may need to be special
        $this->assertTrue(  Search::isSpecial('faith hope charity','proximity') );
        $this->assertTrue(  Search::isSpecial('faith hope charity','chapter') );
        $this->assertTrue(  Search::isSpecial('faith hope charity','book') );
        $this->assertFalse( Search::isSpecial('faith CHAP hope charity','and') );
        $this->assertFalse( Search::isSpecial('faith CHAP hope PROX(4) charity','or') );
        $this->assertFalse( Search::isSpecial('faith chap hope charity','boolean') ); // Case sensitive, so false
        $this->assertTrue(  Search::isSpecial('faith CHAP hope charity','boolean') );
        $this->assertTrue(  Search::isSpecial('faith PROX(4) hope PROX(12) charity','boolean') );
        $this->assertTrue(  Search::isSpecial('faith BOOK hope CHAP charity','boolean') );
    }
    
    public function testBooleanize() {
        $search = 'faith hope joy';
        $bp = Search::booleanizeQuery($search, 'all_words');
        $this->assertEquals('faith hope joy', $bp);
        $bp = Search::booleanizeQuery($search, 'and');
        $this->assertEquals('faith hope joy', $bp);
        $bp = Search::booleanizeQuery($search, 'boolean');
        $this->assertEquals('faith hope joy', $bp);
        $bp = Search::booleanizeQuery($search, 'any_word');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = Search::booleanizeQuery($search, 'or');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = Search::booleanizeQuery($search, 'phrase');
        $this->assertEquals('"faith hope joy"', $bp);
        $bp = Search::booleanizeQuery($search, 'not');
        $this->assertEquals('NOT (faith hope joy)', $bp);
        $bp = Search::booleanizeQuery($search, 'proximity', 5);
        $this->assertEquals('faith PROX(5) hope PROX(5) joy', $bp);
        $bp = Search::booleanizeQuery($search, 'proximity', 50);
        $this->assertEquals('faith PROX(50) hope PROX(50) joy', $bp);
        $bp = Search::booleanizeQuery($search, 'book');
        $this->assertEquals('faith BOOK hope BOOK joy', $bp);
        
        $bp = Search::booleanizeQuery('faith AND (hope OR love)', 'boolean');
        $this->assertEquals('faith AND (hope OR love)', $bp);
    }
    
    public function testBooleanParse() {
        $parsed = Search::parseQueryTerms('faith AND (hope OR love)');
        $this->assertEquals(array('faith', 'hope', 'love'), $parsed);
        $parsed = Search::parseQueryTerms('faith AND (hope OR love) OR "shall be saved"');
        $this->assertEquals(array('faith', 'hope', 'love', '"shall be saved"'), $parsed);
        $parsed = Search::parseQueryTerms('faith AND hope PROX(14) charity');
        $this->assertEquals(array('faith', 'hope', 'charity'), $parsed);
        $parsed = Search::parseQueryTerms('faith CHAP hope BOOK charity');
        $this->assertEquals(array('faith', 'hope', 'charity'), $parsed);
        $parsed = Search::parseQueryTerms('(faith OR hope) charity PROX(12) (Joy or love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'or', 'love'), $parsed); // lowercase or is considered a keyword
        $parsed = Search::parseQueryTerms('(faith OR hope) charity PROX(12) (Joy OR love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'love'), $parsed); 
    }
    
    public function testBooleanStandardization() {
        $std = Search::standardizeBoolean('faith hope love');
        $this->assertEquals('faith AND hope AND love', $std);
        $std = Search::standardizeBoolean('faith hope AND love');
        $this->assertEquals('faith AND hope AND love', $std);
        
//        $std = Search::standardizeBoolean('(faith OR hope) charity PROX(12) (Joy OR love)');
//        $this->assertEquals('(faith OR hope) AND charity PROX(12) (Joy OR love)', $std);
    }
}
