<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Search;

class SearchTest extends TestCase
{
    public function testEmptySearch() 
    {
        $empty = array('', NULL, FALSE, array());

        foreach($empty as $val) {
            $Search = Search::parseSearch($val);
            $this->assertFalse($Search);
        }
    }

    static public function methodIsSpecialDataProvider() 
    {
        return [
            ['faith hope charity', 'and', false],
            ['faith hope charity', 'or', false],
            ['faith hope charity', 'phrase', false],
            ['faith hope charity', 'regexp', false],
            ['faith hope charity', 'boolean', false],
            ['faith hope charity', 'strongs', false], // This may need to be special
            ['faith hope charity', 'proximity', true],
            ['faith hope charity', 'chapter', true],
            ['faith hope charity', 'book', true],
            ['faith CHAP hope charity', 'and', true],
            ['faith chap hope charity', 'and', false], // Case sensitive, so false
            ['faith CHAP hope PROX(4) charity', 'or', false],
            ['faith chap hope charity', 'boolean', false], // Case sensitive, so false
            ['faith CHAP hope charity', 'boolean', true],
            ['faith PROX(4) hope PROX(12) charity', 'boolean', true],
            ['faith PROX(4) hope PROX(12) charity', 'and', true],
            ['faith BOOK hope CHAP charity', 'boolean', true]
        ];
    }

    #[DataProvider('methodIsSpecialDataProvider')]
    public function testMethodIsSpecial($query, $type, $expected)
    {
        $this->assertSame($expected, Search::isSpecial($query, $type));
    }

    static public function booleanizeQueryDataProvider() 
    {
        return [
            ['faith hope joy', 'all_words', 'faith hope joy'],
            ['faith hope joy', 'and', 'faith hope joy'],
            ['faith hope joy', 'boolean', 'faith hope joy'],
            ['faith hope joy', 'any_word', 'faith OR hope OR joy'],
            ['faith hope joy', 'or', 'faith OR hope OR joy'],
            ['faith hope joy', 'phrase', '"faith hope joy"'],
            ['faith hope joy', 'not', 'NOT (faith hope joy)'],
            ['faith hope joy', 'proximity', 'faith PROX(5) hope PROX(5) joy', 5],
            ['faith hope joy', 'proximity', 'faith PROX(50) hope PROX(50) joy', 50],
            ['faith hope joy', 'book', 'faith BOOK hope BOOK joy'],
            ['faith AND (hope OR love)', 'boolean', 'faith AND (hope OR love)']
        ];
    }

    #[DataProvider('booleanizeQueryDataProvider')]
    public function testBooleanizeQuery($search, $type, $expected, $prox = null)
    {
        $Search = new Search();
        $bp = $Search->booleanizeQuery($search, $type, $prox);
        $this->assertEquals($expected, $bp);
    }

    static public function parseQueryTermsDataProvider() 
    {
        return [
            ['faith AND (hope OR love)', ['faith', 'hope', 'love']],
            ['faith AND (hope OR love) OR "shall be saved"', ['faith', 'hope', 'love', '"shall be saved"']],
            ["faith AND (hope OR love) OR 'shall be saved'", ['faith', 'hope', 'love', '\'shall', 'be', 'saved\'']],
            ["won't be lost", ['won\'t', 'be', 'lost']], // conjunction
            ['faith AND hope PROX(14) charity', ['faith', 'hope', 'charity']],
            ['faith CHAP hope BOOK charity', ['faith', 'hope', 'charity']],

            // When in all caps, chapter and book are interpreted as operators
            ['faith CHAPTER hope BOOK charity', ['faith', 'hope', 'charity']],

            // When in all lower case, chapter and book are interpreted as keywords
            ['faith chapter hope book charity', ['faith', 'chapter', 'hope', 'book', 'charity']],
            ['(faith OR hope) charity PROX(12) (Joy or love)', ['faith', 'hope', 'charity', 'Joy', 'or', 'love']],
            ['(faith OR hope) charity PROX(12) (Joy OR love)', ['faith', 'hope', 'charity', 'Joy', 'love']],
            ['(faith OR hope) charity PROC(12) (Joy OR love)', ['faith', 'hope', 'charity', 'Joy', 'love']],
            ['(faith OR hope) charity PROXC(12) (Joy OR love)', ['faith', 'hope', 'charity', 'Joy', 'love']]
        ];
    }

    #[DataProvider('parseQueryTermsDataProvider')]
    public function testParseQueryTerms($query, $expected)
    {
        $parsed = Search::parseQueryTerms($query);
        $this->assertEquals($expected, $parsed);
    }

    public function testParseQueryTermsRegexp(): void
    {
        $parsed = Search::parseQueryTerms('`gr[ae]y matt?er` AND faith');
        $this->assertContains('`gr[ae]y matt?er`', $parsed);
        $this->assertCount(2, $parsed);
    }

    public static function standardizeBooleanDataProvider() 
    {
        return [
            // Make sure we haven't broke inherited functionality
            ['faith hope love', 'faith AND hope AND love'],
            ['faith hope AND love', 'faith AND hope AND love'],
            ['faith AND (hope OR love)', 'faith AND (hope OR love)'],
            ['faith & (hope ||  love)  ', 'faith AND (hope OR love)'],
            ['faith (hope OR love)', 'faith AND (hope OR love)'],
            ['faith (hope AND love)', 'faith AND (hope AND love)'],
            ['faith (hope love)', 'faith AND (hope AND love)'],
            ['faith (hope love)  joy', 'faith AND (hope AND love) AND joy'],

            // Testing added functionality
            ['(faith OR hope) charity PROX(12) (Joy or love)', '(faith OR hope) AND charity PROX(12) (Joy AND or AND love)'],
            ['(faith OR hope) charity PROC(12) (Joy or love)', '(faith OR hope) AND charity PROC(12) (Joy AND or AND love)'],
            ['faith CHAP hope BOOK charity', 'faith CHAP hope BOOK charity'],
            // When in all caps, chapter and book are interpreted as operators
            ['faith CHAPTER hope BOOK charity', 'faith CHAP hope BOOK charity'],
            // When in all lower case, chapter and book are interpreted as keywords
            ['faith chapter hope book charity', 'faith AND chapter AND hope AND book AND charity'],
            ['(hour | time | day | moment) (tempt% | try% )', '(hour OR time OR day OR moment) AND (tempt% OR try% )']
        ];
    }

    #[DataProvider('standardizeBooleanDataProvider')]
    public function testStandardizeBoolean($input, $expected)
    {
        $std = Search::standardizeBoolean($input);
        $this->assertEquals($expected, $std);
    }

    public static function parseSearchWithProximityDataProvider() 
    {
        return [
            ['(faith OR hope) charity CHAPTER (Joy or love)', [['~c'], '((faith OR hope) charity)', '((Joy or love))']],
            ['(faith OR hope) charity BOOK (Joy or love)', [['~b'], '((faith OR hope) charity)', '((Joy or love))']],
            ['(faith OR hope) charity PROX (Joy or love)', [['~p'], '(faith OR hope) charity', '(Joy or love)'], 'boolean', true],
            ['(faith OR hope) charity PROX(15) (Joy or love)', [['~p(15)'], '(faith OR hope) charity', '(Joy or love)'], 'boolean', true],
            ['faith PROX(4) hope PROX(12) charity', [['~p(4)', '~p(12)'], 'faith', 'hope', 'charity'], 'boolean', true],
            ['faith PROC(4) hope PROC(12) charity', [['~l(4)', '~l(12)'], 'faith', 'hope', 'charity'], 'boolean', true],
            ['faith hope charity', [['~p(5)', '~p(5)'], 'faith', 'hope', 'charity'], 'proximity'],
            ['faith hope charity', [['~b', '~b'], 'faith', 'hope', 'charity'], 'book'],
            ['faith hope charity', [['~c', '~c'], 'faith', 'hope', 'charity'], 'chapter'],
            ['faith | joy CHAP hope AND love BOOK charity', [['~c', '~b'], '(faith | joy)', '(hope AND love)', '(charity)']]
        ];
    }

    #[DataProvider('parseSearchWithProximityDataProvider')]
    public function testParseSearchWithProximity($input, $expected, $search_type = 'boolean', $disable_paren_wrap = false)
    {
        $Search = Search::parseSearch($input, ['search_type' => $search_type]);
        $this->assertTrue($Search->is_special);
        list($Searches, $operators) = $Search->parseProximitySearch($disable_paren_wrap);
        $this->assertEquals($expected[0], $operators);
        $this->assertEquals($expected[1], $Searches[0]->search);
        $this->assertEquals($expected[2], $Searches[1]->search);

        if (isset($expected[3])) {
            $this->assertEquals($expected[3], $Searches[2]->search);
        }
    }

    public function testIsSpecialReturnsTrueForProximityType()
    {
        $this->assertTrue(Search::isSpecial('word1 ~p(3) word2', 'proximity'));
    }

    public function testIsSpecialReturnsTrueForProximityOperator()
    {
        $this->assertTrue(Search::isSpecial('love ~p(5) God', 'and'));
    }

    public function testIsSpecialReturnsFalseForNormalSearch()
    {
        $this->assertFalse(Search::isSpecial('love God', 'and'));
    }

    public function testContainsProximityOperatorsDetectsProximity()
    {
        $this->assertTrue(Search::containsProximityOperators('Jesus ~p(2) Christ'));
        $this->assertTrue(Search::containsProximityOperators('faith ~c hope'));
        $this->assertFalse(Search::containsProximityOperators('love and peace'));
    }

    public function testIsTermStrongs()
    {
        $this->assertTrue(Search::isTermStrongs('G123'));
        $this->assertTrue(Search::isTermStrongs('h456'));
        $this->assertFalse(Search::isTermStrongs('word'));
        $this->assertFalse(Search::isTermStrongs('123G'));
    }

    public function testStandardizeBooleanAI()
    {
        $query = 'love ~p(3) God';
        $standardized = Search::standardizeBoolean($query);
        $this->assertStringContainsString('PROX', $standardized);
    }

    public function testParseQueryTermsRemovesOperators()
    {
        $query = 'love PROX(3) God CHAP hope';
        $terms = Search::parseQueryTerms($query);
        $this->assertContains('love', $terms);
        $this->assertContains('God', $terms);
        $this->assertContains('hope', $terms);
        $this->assertNotContains('PROX', $terms);
        $this->assertNotContains('CHAP', $terms);
    }

    public function testGetTermTypeReturnsStrongs()
    {
        $this->assertEquals('strongs', Search::getTermType('G123'));
    }

    public function testGetTermTypeFallsBackToParent()
    {
        $this->assertNotEquals('strongs', Search::getTermType('love'));
    }

    public function testStandardizeProximityOperators()
    {
        $query = 'love ~ God CHAPTER hope BOOK faith';
        $result = Search::standardizeProximityOperators($query);
        $this->assertStringContainsString('~p', $result);
        $this->assertStringContainsString('~c', $result);
        $this->assertStringContainsString('~b', $result);
    }

    public function testParseStrongsReturnsStrongsTerms()
    {
        $terms = Search::parseStrongs('G123 love H456');
        $this->assertContains('G123', $terms);
        $this->assertContains('H456', $terms);
        $this->assertNotContains('love', $terms);
    }

    public function testBooleanizeQueryForProximity()
    {
        $search = new Search();
        $result = $search->booleanizeQuery('love God', 'proximity', 4);
        $this->assertEquals('love PROX(4) God', $result);
    }

    public function testBooleanizeQueryForBook()
    {
        $search = new Search();
        $result = $search->booleanizeQuery('love God', 'book');
        $this->assertEquals('love BOOK God', $result);
    }

    public function testBooleanizeQueryFallsBackToParent()
    {
        $search = new Search();
        $result = $search->booleanizeQuery('love God', 'boolean');
        $this->assertEquals('love God', $result);
    }

    public function testGetIsSpecialProperty()
    {
        $search = new Search();
        $search->setSearch('love ~p(3) God');
        $this->assertTrue($search->is_special);
        $this->assertTrue($search->__get('is_special'));
    }

    public function _testSetSearchSetsIsSpecial()
    {
        $this->markTestIncomplete('Mockbuilder not found.');

        $search = $this->getMockBuilder(Search::class)
            ->disableOriginalConstructor()
            ->setMethods(['setSearch', 'isSpecial'])
            ->getMock();

        $search->expects($this->once())
            ->method('isSpecial')
            ->with('test', null)
            ->willReturn(true);

        $search->search_type = null;
        $search->setSearch('test');
        $this->assertTrue($search->is_special);
    }

    public function testIsSpecialDetectsProximity()
    {
        $this->assertTrue(Search::isSpecial('word1 ~p(3) word2', 'and'));
        $this->assertFalse(Search::isSpecial('word1 word2', 'and'));
        $this->assertTrue(Search::isSpecial('word1', 'proximity'));
        $this->assertFalse(Search::isSpecial('word1', 'boolean'));
    }

    public function testContainsProximityOperators()
    {
        $this->assertTrue(Search::containsProximityOperators('hello ~p(5) world'));
        $this->assertTrue(Search::containsProximityOperators('foo ~c bar'));
        $this->assertFalse(Search::containsProximityOperators('foo bar'));
    }

    public function testGetTermType()
    {
        $this->assertEquals('strongs', Search::getTermType('G123'));
        $this->assertNotEquals('strongs', Search::getTermType('word'));
    }
}