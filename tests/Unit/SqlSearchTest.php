<?php

namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\SqlSearch;

class SqlSearchTest extends TestCase
{
    
    #[DataProvider('booleanizeQueryDataProvider')]
    public function testBooleanizeQuery(string $search, string $type, string $expected)
    {
        $SqlSearch = new SqlSearch();
        $result = $SqlSearch->booleanizeQuery($search, $type);
        $this->assertEquals($expected, $result);
    }

    public static function booleanizeQueryDataProvider()
    {
        return [
            ['faith hope joy', 'all_words', 'faith hope joy'],
            ['faith hope joy', 'and', 'faith hope joy'],
            ['faith hope joy', 'boolean', 'faith hope joy'],
            ['faith hope joy', 'any_word', 'faith OR hope OR joy'],
            ['faith hope joy', 'or', 'faith OR hope OR joy'],
            ['faith hope joy', 'phrase', '"faith hope joy"'],
            ['faith hope joy', 'not', 'NOT (faith hope joy)'],
        ];
    }

    #[DataProvider('parseQueryTermsDataProvider')]
    public function testParseQueryTerms(string $search, array $expected)
    {
        $result = SqlSearch::parseQueryTerms($search);
        $this->assertEquals($expected, $result);
    }

    public static function parseQueryTermsDataProvider()
    {
        return [
            ['faith hope love', ['faith', 'hope', 'love']],
            ['faith AND (hope OR love)', ['faith', 'hope', 'love']],
            ['faith AND (hope OR love) OR "shall be saved"', ['faith', 'hope', 'love', '"shall be saved"']],
            ['(faith OR hope) charity AND (Joy or love)', ['faith', 'hope', 'charity', 'Joy', 'or', 'love']],
            ['(faith OR hope) charity AND (Joy OR love)', ['faith', 'hope', 'charity', 'Joy', 'love']],
            ['(faith OR hope) charity AND "free spirit"', ['faith', 'hope', 'charity', '"free spirit"']],
        ];
    }
    
    #[DataProvider('standardizeBooleanDataProvider')]
    public function testStandardizeBoolean(string $search, string $expected)
    {
        $result = SqlSearch::standardizeBoolean($search);
        $this->assertEquals($expected, $result);
    }

    public static function standardizeBooleanDataProvider()
    {
        return [
            ['faith hope love', 'faith AND hope AND love'],
            ['faith hope AND love', 'faith AND hope AND love'],
            ['faith AND (hope OR love)', 'faith AND (hope OR love)'],
            ['faith & (hope ||  love)  ', 'faith AND (hope OR love)'],
            ['faith (hope OR love)', 'faith AND (hope OR love)'],
            ['faith (hope AND love)', 'faith AND (hope AND love)'],
            ['faith (hope love)', 'faith AND (hope AND love)'],
            ['faith (hope love)  joy', 'faith AND (hope AND love) AND joy'],
            ['faith (hope love) "free spirit"', 'faith AND (hope AND love) AND "free spirit"'],
            // Single quotes do NOT identify a phrase, instead they are treated as part of the keyword
            ['faith (hope love) \'free spirit\'', 'faith AND (hope AND love) AND \'free AND spirit\''],
        ];
    }

    public function testBuildTwoOrMoreQueryFourOne() 
    {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 1);
        $this->assertEquals(implode(' OR ', $keywords), $query);
    }

    public function testBuildTwoOrMoreQueryFourTwo() 
    {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 2);
        $expected = 't1 AND t2 OR t1 AND t3 OR t1 AND t4 OR t2 AND t3 OR t2 AND t4 OR t3 AND t4';
        $this->assertEquals($expected, $query);
    }

    public function testBuildTwoOrMoreQueryFourThree() 
    {
        $keywords = $this->_generateKeywords(4);
        $expected = 't1 AND t2 AND t3 OR t1 AND t2 AND t4 OR t1 AND t3 AND t4 OR t2 AND t3 AND t4';
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 3);
        $this->assertEquals($expected, $query);
    }

    public function testBuildTwoOrMoreQueryFourFour() 
    {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 4);
        $this->assertEquals(implode(' AND ', $keywords), $query);
    }

    public function testBuildTwoOrMoreQueryFourFive() 
    {
        $keywords = $this->_generateKeywords(4);
        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 5);
        $this->assertEquals(implode(' AND ', $keywords), $query);
    }

    public function testBuildTwoOrMoreQueryFiveThree() 
    {
        $keywords = $this->_generateKeywords(5);
        $expected = 't1 AND t2 AND t3 OR t1 AND t2 AND t4 OR t1 AND t2 AND t5 OR t1 AND t3 AND t4 OR t1 AND t3 AND t5 OR '
                . 't1 AND t4 AND t5 OR t2 AND t3 AND t4 OR t2 AND t3 AND t5 OR t2 AND t4 AND t5 OR t3 AND t4 AND t5';

        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 3);
        $this->assertEquals($expected, $query);
    }

    public function testBuildTwoOrMoreQuerySixTwo() 
    {
        $keywords = $this->_generateKeywords(6);
        $expected = 't1 AND t2 OR t1 AND t3 OR t1 AND t4 OR t1 AND t5 OR t1 AND t6 OR t2 AND t3 OR t2 AND t4 OR t2 AND t5 OR t2 AND t6'
                . ' OR t3 AND t4 OR t3 AND t5 OR t3 AND t6 OR t4 AND t5 OR t4 AND t6 OR t5 AND t6';

        $query = SqlSearch::buildTwoOrMoreQuery($keywords, 2);
        $this->assertEquals($expected, $query);
    }



    // AI generated test cases for SqlSearch class
    
    public function testSetSearchTrimsAndNormalizesWhitespace()
    {
        $search = new SqlSearch("  hello   world  ");
        $this->assertEquals("hello world", $search->search);
    }

    public function testRemoveUnsafeCharactersRemovesUnwantedPunctuation()
    {
        $input = "Hello, world! (test) — „“”";
        $output = SqlSearch::removeUnsafeCharacters($input);
        $this->assertStringNotContainsString('„', $output);
        $this->assertStringNotContainsString('“', $output);
        $this->assertStringNotContainsString('”', $output);
        $this->assertStringContainsString('Hello world test', $output);
    }

    public function testParseSimpleQueryTermsSplitsAndDeduplicates()
    {
        $terms = SqlSearch::parseSimpleQueryTerms("foo bar foo baz");
        $this->assertEquals(['foo', 'bar', 'baz'], $terms);
    }

    public function testIsTermPhraseDetectsPhrase()
    {
        $this->assertTrue(SqlSearch::isTermPhrase('"hello world"'));
        $this->assertFalse(SqlSearch::isTermPhrase('hello'));
    }

    public function testIsTermRegexpDetectsRegexp()
    {
        $this->assertTrue(SqlSearch::isTermRegexp('`pattern`'));
        $this->assertFalse(SqlSearch::isTermRegexp('pattern'));
    }

    public function testStandardizeBooleanReplacesOperators()
    {
        $query = 'foo && bar || baz !qux';
        $std = SqlSearch::standardizeBoolean($query);
        $this->assertStringContainsString('AND', $std);
        $this->assertStringContainsString('OR', $std);
        $this->assertStringContainsString('NOT', $std);
    }

    public function testParseQueryTermsExtractsKeywords()
    {
        $query = 'foo AND "bar baz" OR `pattern`';
        $terms = SqlSearch::parseQueryTerms($query);
        $this->assertContains('foo', $terms);
        $this->assertContains('"bar baz"', $terms);
        $this->assertContains('`pattern`', $terms);
    }

    private function _generateKeywords($count = 2) 
    {
        $keywords = [];

        for($i = 1; $i <= $count; $i++) {
            $keywords[] = 't' . $i;
        }

        return $keywords;
    }
}