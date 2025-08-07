<?php

namespace Tests\Feature;
use Tests\TestCase;
use App\SqlSearch;

class SqlSearchTest extends TestCase
{

    // FEATURE TEST
    public function testBindDataPush() 
    {
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

    // FEATURE TEST
    public function testSqlGeneration() 
    {
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
        $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '%free%spirit%', ':bd3' => 'free([^a-fi-zA-FI-Z]+)spirit'), $binddata);

        $Search = SqlSearch::parseSearch('faith', array('whole_words' => 'on'));
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2)', $sql);
        if(config('database.mysql.new_regexp')) {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '\\bfaith\\b'), $binddata);
        } else {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '([[:<:]]|[‹])faith([[:>:]]|[›])'), $binddata);
        }

        $Search = SqlSearch::parseSearch('faith% ', array('whole_words' => 'on'));
        list($sql, $binddata) = $Search->generateQuery();
        $this->assertEquals('(`text` LIKE :bd1 AND `text` REGEXP :bd2)', $sql);

        if(config('database.mysql.new_regexp')) {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '\\bfaith'), $binddata);
        } else {
            $this->assertEquals(array(':bd1' => '%faith%', ':bd2' => '([[:<:]]|[‹])faith'), $binddata);
        }

    }

    public function testAdvancedQuery() 
    {
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
        $this->assertEquals(array(':bd1' => '%faith%hope%love%', ':bd2' => 'faith([^a-fi-zA-FI-Z]+)hope([^a-fi-zA-FI-Z]+)love'), $binddata);
    }
}