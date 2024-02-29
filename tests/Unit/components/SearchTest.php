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
        $this->assertTrue( Search::isSpecial('faith CHAP hope charity','and') );
        $this->assertFalse( Search::isSpecial('faith chap hope charity','and') ); // Case sensitive, so false
        $this->assertFalse( Search::isSpecial('faith CHAP hope PROX(4) charity','or') );
        $this->assertFalse( Search::isSpecial('faith chap hope charity','boolean') ); // Case sensitive, so false
        $this->assertTrue(  Search::isSpecial('faith CHAP hope charity','boolean') );
        $this->assertTrue(  Search::isSpecial('faith PROX(4) hope PROX(12) charity','boolean') );
        $this->assertTrue(  Search::isSpecial('faith PROX(4) hope PROX(12) charity','and') );
        $this->assertTrue(  Search::isSpecial('faith BOOK hope CHAP charity','boolean') );
    }

    public function testBooleanize() {
        $search = 'faith hope joy';
        $Search = new Search();
        $bp = $Search->booleanizeQuery($search, 'all_words');
        $this->assertEquals('faith hope joy', $bp);
        $bp = $Search->booleanizeQuery($search, 'and');
        $this->assertEquals('faith hope joy', $bp);
        $bp = $Search->booleanizeQuery($search, 'boolean');
        $this->assertEquals('faith hope joy', $bp);
        $bp = $Search->booleanizeQuery($search, 'any_word');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = $Search->booleanizeQuery($search, 'or');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = $Search->booleanizeQuery($search, 'phrase');
        $this->assertEquals('"faith hope joy"', $bp);
        $bp = $Search->booleanizeQuery($search, 'not');
        $this->assertEquals('NOT (faith hope joy)', $bp);
        $bp = $Search->booleanizeQuery($search, 'proximity', 5);
        $this->assertEquals('faith PROX(5) hope PROX(5) joy', $bp);
        $bp = $Search->booleanizeQuery($search, 'proximity', 50);
        $this->assertEquals('faith PROX(50) hope PROX(50) joy', $bp);
        $bp = $Search->booleanizeQuery($search, 'book');
        $this->assertEquals('faith BOOK hope BOOK joy', $bp);
        $bp = $Search->booleanizeQuery('faith AND (hope OR love)', 'boolean');
        $this->assertEquals('faith AND (hope OR love)', $bp);
    }

    public function testBooleanParse() {
        $parsed = Search::parseQueryTerms('faith AND (hope OR love)');
        $this->assertEquals(array('faith', 'hope', 'love'), $parsed);
        $parsed = Search::parseQueryTerms('faith AND (hope OR love) OR "shall be saved"');
        $this->assertEquals(array('faith', 'hope', 'love', '"shall be saved"'), $parsed);
        $parsed = Search::parseQueryTerms("faith AND (hope OR love) OR 'shall be saved'");
        $this->assertEquals(array('faith', 'hope', 'love', '\'shall', 'be', 'saved\''), $parsed);
        $parsed = Search::parseQueryTerms("won't be lost"); // Conjunction
        $this->assertEquals(array('won\'t', 'be', 'lost'), $parsed);
        $parsed = Search::parseQueryTerms('faith AND hope PROX(14) charity');
        $this->assertEquals(array('faith', 'hope', 'charity'), $parsed);
        $parsed = Search::parseQueryTerms('faith CHAP hope BOOK charity');
        $this->assertEquals(array('faith', 'hope', 'charity'), $parsed);
        
        // When in all caps, chapter and book are interpreted as operators
        $parsed = Search::parseQueryTerms('faith CHAPTER hope BOOK charity');
        $this->assertEquals(array('faith', 'hope', 'charity'), $parsed);
        
        // When in all lower case, chapter and book are interpreted as keywords
        $parsed = Search::parseQueryTerms('faith chapter hope book charity');
        $this->assertEquals(array('faith', 'chapter', 'hope', 'book', 'charity'), $parsed);
        $parsed = Search::parseQueryTerms('(faith OR hope) charity PROX(12) (Joy or love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'or', 'love'), $parsed); // lowercase 'or' is considered a keyword
        
        $parsed = Search::parseQueryTerms('(faith OR hope) charity PROX(12) (Joy OR love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'love'), $parsed);
        $parsed = Search::parseQueryTerms('(faith OR hope) charity PROC(12) (Joy OR love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'love'), $parsed);
        $parsed = Search::parseQueryTerms('(faith OR hope) charity PROXC(12) (Joy OR love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'love'), $parsed);
        
        // Regexp
        $parsed = Search::parseQueryTerms('`gr[ae]y matt?er` AND faith');
        //$this->assertEquals(array('`gr[ae]y matt?er`', 'faith'), $parsed);
        $this->assertContains('`gr[ae]y matt?er`', $parsed);
        $this->assertCount(2, $parsed);
    }

    public function testBooleanStandardization() {
        // Make sure we haven't broke inherited functionality
        $std = Search::standardizeBoolean('faith hope love');
        $this->assertEquals('faith AND hope AND love', $std);
        $std = Search::standardizeBoolean('faith hope AND love');
        $this->assertEquals('faith AND hope AND love', $std);
        $std = Search::standardizeBoolean('faith AND (hope OR love)');
        $this->assertEquals('faith AND (hope OR love)', $std);
        $std = Search::standardizeBoolean('faith & (hope ||  love)  ');
        $this->assertEquals('faith AND (hope OR love)', $std);
        $std = Search::standardizeBoolean('faith (hope OR love)');
        $this->assertEquals('faith AND (hope OR love)', $std);
        $std = Search::standardizeBoolean('faith (hope AND love)');
        $this->assertEquals('faith AND (hope AND love)', $std);
        $std = Search::standardizeBoolean('faith (hope love)');
        $this->assertEquals('faith AND (hope AND love)', $std);
        $std = Search::standardizeBoolean('faith (hope love)  joy');
        $this->assertEquals('faith AND (hope AND love) AND joy', $std);

        // Testing added functionality
        $std = Search::standardizeBoolean('(faith OR hope) charity PROX(12) (Joy or love)');
        $this->assertEquals('(faith OR hope) AND charity PROX(12) (Joy AND or AND love)', $std);
        $std = Search::standardizeBoolean('(faith OR hope) charity PROC(12) (Joy or love)');
        $this->assertEquals('(faith OR hope) AND charity PROC(12) (Joy AND or AND love)', $std);
        $std = Search::standardizeBoolean('faith CHAP hope BOOK charity');
        $this->assertEquals('faith CHAP hope BOOK charity', $std);
        // When in all caps, chapter and book are interpreted as operators
        $std = Search::standardizeBoolean('faith CHAPTER hope BOOK charity');
        $this->assertEquals('faith CHAP hope BOOK charity', $std);
        // When in all lower case, chapter and book are interpreted as keywords
        $std = Search::standardizeBoolean('faith chapter hope book charity');
        $this->assertEquals('faith AND chapter AND hope AND book AND charity', $std);

        $std = Search::standardizeBoolean('(hour | time | day | moment) (tempt% | try% )');
        $this->assertEquals('(hour OR time OR day OR moment) AND (tempt% OR try% )', $std);

        //$std = Search::standardizeBoolean('(faith OR hope) charity PROX(bacon) (Joy or love)');
        //$this->assertEquals('(faith OR hope) AND charity PROX(bacon) (Joy AND or AND love)', $std);
    }

    public function testSqlGeneration() {
        $Search = Search::parseSearch('faith hope love');
        $search_type = $Search->search_type;
        $this->assertEquals('and', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) AND (`text` LIKE :bd2) AND (`text` LIKE :bd3)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);

        $Search = Search::parseSearch('faith hope love', array('search_type' => 'or'));
        $this->assertEquals('or', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) OR (`text` LIKE :bd2) OR (`text` LIKE :bd3)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);
    }

    public function testProximityParsing() {
        $Search = Search::parseSearch('(faith OR hope) charity CHAPTER (Joy or love)', ['search_type' => 'boolean']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch();
        $this->assertEquals(array('~c'), $operators);
        $this->assertEquals('((faith OR hope) charity)', $Searches[0]->search);
        $this->assertEquals('((Joy or love))', $Searches[1]->search);

        $Search = Search::parseSearch('(faith OR hope) charity BOOK (Joy or love)', ['search_type' => 'boolean']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch();
        $this->assertEquals(array('~b'), $operators);
        $this->assertEquals('((faith OR hope) charity)', $Searches[0]->search);
        $this->assertEquals('((Joy or love))', $Searches[1]->search);

        $Search = Search::parseSearch('(faith OR hope) charity PROX (Joy or love)', ['search_type' => 'boolean']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch(TRUE);
        $this->assertEquals(array('~p'), $operators);
        $this->assertEquals('(faith OR hope) charity', $Searches[0]->search);
        $this->assertEquals('(Joy or love)', $Searches[1]->search);

        $Search = Search::parseSearch('(faith OR hope) charity PROX(15) (Joy or love)', ['search_type' => 'boolean']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch(TRUE);
        $this->assertEquals(array('~p(15)'), $operators);
        $this->assertEquals('(faith OR hope) charity', $Searches[0]->search);
        $this->assertEquals('(Joy or love)', $Searches[1]->search);

        $Search = Search::parseSearch('faith PROX(4) hope PROX(12) charity', ['search_type' => 'boolean']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch(TRUE);
        $this->assertEquals(array('~p(4)','~p(12)'), $operators);
        $this->assertEquals('faith',   $Searches[0]->search);
        $this->assertEquals('hope',    $Searches[1]->search);
        $this->assertEquals('charity', $Searches[2]->search);

        $Search = Search::parseSearch('faith PROC(4) hope PROC(12) charity', ['search_type' => 'boolean']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch(TRUE);
        $this->assertEquals(array('~l(4)','~l(12)'), $operators);
        $this->assertEquals('faith',   $Searches[0]->search);
        $this->assertEquals('hope',    $Searches[1]->search);
        $this->assertEquals('charity', $Searches[2]->search);

        $Search = Search::parseSearch('faith hope charity', ['search_type' => 'proximity']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch();
        $this->assertEquals(array('~p(5)','~p(5)'), $operators);
        $this->assertEquals('faith',   $Searches[0]->search);
        $this->assertEquals('hope',    $Searches[1]->search);
        $this->assertEquals('charity', $Searches[2]->search);

        $Search = Search::parseSearch('faith hope charity', ['search_type' => 'book']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch();
        $this->assertEquals(array('~b','~b'), $operators);
        $this->assertEquals('faith',   $Searches[0]->search);
        $this->assertEquals('hope',    $Searches[1]->search);
        $this->assertEquals('charity', $Searches[2]->search);

        $Search = Search::parseSearch('faith hope charity', ['search_type' => 'chapter']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch();
        $this->assertEquals(array('~c','~c'), $operators);
        $this->assertEquals('faith',   $Searches[0]->search);
        $this->assertEquals('hope',    $Searches[1]->search);
        $this->assertEquals('charity', $Searches[2]->search);

        $Search = Search::parseSearch('faith | joy CHAP hope AND love BOOK charity', ['search_type' => 'boolean']);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch();
        $this->assertEquals(array('~c','~b'), $operators);
        $this->assertEquals('(faith | joy)',   $Searches[0]->search);
        $this->assertEquals('(hope AND love)', $Searches[1]->search);
        $this->assertEquals('(charity)',       $Searches[2]->search);
    }

    function testWildcardParse() {
        $query = 'tempt% world';
        $terms = Search::parseQueryTerms($query);
        $this->assertCount(2, $terms);

        // With whole words
        $Search = Search::parseSearch($query, ['whole_words' => TRUE]);
        $this->assertEquals('and', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();

        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2) AND (`text` LIKE :bd3 AND `text` REGEXP :bd4)', $sql);
        
        if(config('database.mysql.new_regexp')) {
            $this->assertEquals(array(':bd1' => '%tempt%', ':bd2' => '\\btempt', ':bd3' => '%world%', ':bd4' => '\\bworld\\b'), $binddata);
        } else {
            $this->assertEquals(array(':bd1' => '%tempt%', ':bd2' => '([[:<:]]|[‹])tempt', ':bd3' => '%world%', ':bd4' => '([[:<:]]|[‹])world([[:>:]]|[›])'), $binddata);            
        }

        $query = 'tempt% %world';
        $Search = Search::parseSearch($query, ['whole_words' => TRUE]);
        $this->assertEquals('and', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();

        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2) AND (`text` LIKE :bd3 AND `text` REGEXP :bd4)', $sql);

        if(config('database.mysql.new_regexp')) {
            $this->assertEquals(array(':bd1' => '%tempt%', ':bd2' => '\\btempt', ':bd3' => '%world%', ':bd4' => 'world\\b'), $binddata);
        } else {
            $this->assertEquals(array(':bd1' => '%tempt%', ':bd2' => '([[:<:]]|[‹])tempt', ':bd3' => '%world%', ':bd4' => 'world([[:>:]]|[›])'), $binddata);
        }

        $query = 'tempt %world';
        $Search = Search::parseSearch($query, ['whole_words' => TRUE]);
        $this->assertEquals('and', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();

        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2) AND (`text` LIKE :bd3 AND `text` REGEXP :bd4)', $sql);
        
        if(config('database.mysql.new_regexp')) {
            $this->assertEquals(array(':bd1' => '%tempt%', ':bd2' => '\\btempt\\b', ':bd3' => '%world%', ':bd4' => 'world\\b'), $binddata);
        } else {
            $this->assertEquals(array(':bd1' => '%tempt%', ':bd2' => '([[:<:]]|[‹])tempt([[:>:]]|[›])', ':bd3' => '%world%', ':bd4' => 'world([[:>:]]|[›])'), $binddata);
        }
    }
}
