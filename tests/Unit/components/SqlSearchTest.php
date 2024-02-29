<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\SqlSearch;

class SqlSearchTest extends TestCase
{
    public function testBooleanize() {
        $search = 'faith hope joy';
        $SqlSearch = new SqlSearch();
        $bp = $SqlSearch->booleanizeQuery($search, 'all_words');
        $this->assertEquals('faith hope joy', $bp);
        $bp = $SqlSearch->booleanizeQuery($search, 'and');
        $this->assertEquals('faith hope joy', $bp);
        $bp = $SqlSearch->booleanizeQuery($search, 'boolean');
        $this->assertEquals('faith hope joy', $bp);
        $bp = $SqlSearch->booleanizeQuery($search, 'any_word');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = $SqlSearch->booleanizeQuery($search, 'or');
        $this->assertEquals('faith OR hope OR joy', $bp);
        $bp = $SqlSearch->booleanizeQuery($search, 'phrase');
        $this->assertEquals('"faith hope joy"', $bp);
        $bp = $SqlSearch->booleanizeQuery($search, 'not');
        $this->assertEquals('NOT (faith hope joy)', $bp);
    }

    public function testBooleanParse() {
        $parsed = SqlSearch::parseQueryTerms('faith AND (hope OR love)');
        $this->assertEquals(array('faith', 'hope', 'love'), $parsed);
        $parsed = SqlSearch::parseQueryTerms('faith AND (hope OR love) OR "shall be saved"');
        $this->assertEquals(array('faith', 'hope', 'love', '"shall be saved"'), $parsed);
        $parsed = SqlSearch::parseQueryTerms('(faith OR hope) charity AND (Joy or love)');
        $this->assertEquals(array('faith', 'hope', 'charity', 'Joy', 'or', 'love'), $parsed); // lowercase or is considered a keyword, not an operator
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

        // Single quotes do NOT identify a phrase, instead they are treated as part of the keyword
        $std = SqlSearch::standardizeBoolean('faith (hope love) \'free spirit\'');
        $this->assertEquals('faith AND (hope AND love) AND \'free AND spirit\'', $std);
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
        $index = SqlSearch::pushToBindData('faith', $binddata);
        //$this->assertEquals(array(':bd1' => 'hey',':bd2' => 'faith', ':bd3' => 'hope',':love4' => 'love'), $binddata);
        //$this->assertEquals(':bd2', $index);
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
        $this->assertEquals('(`text` LIKE :bd1) OR (`text` LIKE :bd2 AND `text` REGEXP :bd3)', $sql);
        // OLD $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%free spirit%', ':bd3' => 'free spirit'), $binddata);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%free%spirit%', ':bd3' => 'free([^a-fi-zA-FI-Z]+)spirit'), $binddata);

        $Search = SqlSearch::parseSearch('faith', array('whole_words' => 'on'));
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2)', $sql);
        // $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '[[:<:]]faith[[:>:]]'), $binddata);
        if(config('database.mysql.new_regexp')) {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '\\bfaith\\b'), $binddata);
        } else {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '([[:<:]]|[‹])faith([[:>:]]|[›])'), $binddata);
        }

        $Search = SqlSearch::parseSearch('faith% ', array('whole_words' => 'on'));
        list($sql, $binddata) = $Search->generateQuery();
        //$this->assertEquals('(`text` REGEXP :bd1)', $sql);
        //$this->assertEquals(array(':bd1' => '[[:<:]]faith'), $binddata);
        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2)', $sql);
        // $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '[[:<:]]faith'), $binddata);

        if(config('database.mysql.new_regexp')) {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '\\bfaith'), $binddata);
        } else {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '([[:<:]]|[‹])faith'), $binddata);
        }

    }

    public function testAdvancedQuery() {
        // All Words
        $Search = SqlSearch::parseSearch(NULL, ['search_all' => 'faith hope love']);
        $this->assertInstanceOf('App\SqlSearch', $Search);
        $this->assertEquals('and', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) AND (`text` LIKE :bd2) AND (`text` LIKE :bd3)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);

        // Any Word
        $Search = SqlSearch::parseSearch(NULL, ['search_any' => 'faith hope love']);
        $this->assertInstanceOf('App\SqlSearch', $Search);
        // Will be AND even though we are doing an OR search - search type only applies to the standard search
        $this->assertEquals('and', $Search->search_type);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) OR (`text` LIKE :bd2) OR (`text` LIKE :bd3)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);

        // One Word (XOR)
        $Search = SqlSearch::parseSearch(NULL, ['search_one' => 'faith hope love']);
        $this->assertInstanceOf('App\SqlSearch', $Search);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1) XOR (`text` LIKE :bd2) XOR (`text` LIKE :bd3)', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);

        // None of the words (NOT)
        $Search = SqlSearch::parseSearch(NULL, ['search_none' => 'faith hope love']);
        $this->assertInstanceOf('App\SqlSearch', $Search);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('NOT ((`text` LIKE :bd1) AND (`text` LIKE :bd2) AND (`text` LIKE :bd3))', $sql);
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%hope%', ':bd3' => '%love%'), $binddata);

        // Exact Phrase
        $Search = SqlSearch::parseSearch(NULL, ['search_phrase' => 'faith hope love']);
        $this->assertInstanceOf('App\SqlSearch', $Search);
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2)', $sql);
        // OLD $this->assertEquals(array(':bd1' => '%faith hope love%', ':bd2' => 'faith hope love'), $binddata);
        $this->assertEquals(array(':bd1' => '%faith%hope%love%', ':bd2' => 'faith([^a-fi-zA-FI-Z]+)hope([^a-fi-zA-FI-Z]+)love'), $binddata);
    }
}
