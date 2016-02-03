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
        $parsed = SqlSearch::parseQueryTerms('(faith OR hope) charity AND "free spirit"');
        $this->assertEquals(array('faith', 'hope', 'charity', '"free spirit"'), $parsed); 
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
        $std = SqlSearch::standardizeBoolean('faith (hope love) "free spirit"');
        $this->assertEquals('faith AND (hope AND love) AND "free spirit"', $std);
    }
    
    public function testBindDataPush() {
        $binddata = array();
        $this->assertEmpty($binddata);
        SqlSearch::pushToBindData('hey', $binddata);
        $this->assertEquals(array(':bd1' => 'hey'), $binddata);
        SqlSearch::pushToBindData('faith', $binddata);
        $this->assertEquals(array(':bd1' => 'hey',':bd2' => 'faith'), $binddata);
        SqlSearch::pushToBindData('hope', $binddata);
        $this->assertEquals(array(':bd1' => 'hey',':bd2' => 'faith', ':bd3' => 'hope'), $binddata);
        SqlSearch::pushToBindData('love', $binddata, 'love');
        $this->assertEquals(array(':bd1' => 'hey',':bd2' => 'faith', ':bd3' => 'hope',':love4' => 'love'), $binddata);
        
        // Attempt to push faith on again - it won't be added because it's already present
        SqlSearch::pushToBindData('faith', $binddata);
        $this->assertEquals(array(':bd1' => 'hey',':bd2' => 'faith', ':bd3' => 'hope',':love4' => 'love'), $binddata);
    }
    
    public function testSqlGeneration() {
        $Search = SqlSearch::parseSearch('faith hope love');
        $search_type = $Search->search_type;
        $this->assertEquals('and', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) AND (`text` LIKE :bd2) AND (`text` LIKE :bd3)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);
        
        $Search = SqlSearch::parseSearch('faith hope love', array('search_type' => 'or'));
        $this->assertEquals('or', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) OR (`text` LIKE :bd2) OR (`text` LIKE :bd3)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);
        
        $Search = SqlSearch::parseSearch('faith | "free spirit"', array('search_type' => 'boolean'));
        $this->assertEquals('boolean', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) OR (`text` REGEXP :bd2)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => 'free spirit'), $binddata);
    }
}
