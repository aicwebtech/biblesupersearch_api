<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\SqlSearch;

class SqlSearchTest extends TestCase
{
    public function testBooleanize() {
        $search = 'faith hope joy';
        $bp = SqlSearch::booleanizeQuery($search, 'all_words');
        $this->assertEquals('faith hope joy', $bp);
        $bp = SqlSearch::booleanizeQuery($search, 'and');
        $this->assertEquals('faith hope joy', $bp);
        $bp = SqlSearch::booleanizeQuery($search, 'boolean');
        $this->assertEquals('faith hope joy', $bp);
        $bp = SqlSearch::booleanizeQuery($search, 'any_word');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = SqlSearch::booleanizeQuery($search, 'or');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = SqlSearch::booleanizeQuery($search, 'phrase');
        $this->assertEquals('"faith hope joy"', $bp);
        $bp = SqlSearch::booleanizeQuery($search, 'not');
        $this->assertEquals('NOT (faith hope joy)', $bp);
    }
    
    public function testBooleanParse() {
        $parsed = SqlSearch::parseQueryTerms('faith AND (hope OR love)');
        $this->assertEquals(array('faith', 'hope', 'love'), $parsed);
        $parsed = SqlSearch::parseQueryTerms('faith AND (hope OR love) OR "shall be saved"');
        $this->assertEquals(array('faith', 'hope', 'love', '"shall be saved"'), $parsed);
        $parsed = SqlSearch::parseQueryTerms('(faith OR hope) charity AND (Joy or love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'or', 'love'), $parsed); // lowercase or is considered a keyword
        $parsed = SqlSearch::parseQueryTerms('(faith OR hope) charity AND (Joy OR love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'love'), $parsed); 
    }
    
    public function testBooleanStandardization() {
        $std = SqlSearch::standardizeBoolean('faith hope love');
        $this->assertEquals('faith AND hope AND love', $std);
        $std = SqlSearch::standardizeBoolean('faith hope AND love');
        $this->assertEquals('faith AND hope AND love', $std);
        $std = SqlSearch::standardizeBoolean('faith AND (hope OR love)');
        $this->assertEquals('faith AND (hope OR love)', $std);
        $std = SqlSearch::standardizeBoolean('faith & (hope ||  love)  ');
        $this->assertEquals('faith AND (hope OR love)', $std);
        $std = SqlSearch::standardizeBoolean('faith (hope OR love)');
        $this->assertEquals('faith AND (hope OR love)', $std);
        $std = SqlSearch::standardizeBoolean('faith (hope AND love)');
        $this->assertEquals('faith AND (hope AND love)', $std);
        $std = SqlSearch::standardizeBoolean('faith (hope love)');
        $this->assertEquals('faith AND (hope AND love)', $std);
        $std = SqlSearch::standardizeBoolean('faith (hope love)  joy');
        $this->assertEquals('faith AND (hope AND love) AND joy', $std);
    }
}
