<?php

namespace Tests\Feature;
use Tests\TestCase;
use App\Search;

class SearchTest extends TestCase
{

    public function testSqlGeneration() 
    {
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

    function testWildcardParse() 
    {
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