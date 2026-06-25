<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use App\Engine;
use App\Passage;
use App\Models\Bible;
use App\Models\Feature;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Schema;

class RequestTest extends TestCase 
{

    /**
     * Request is mapped to 'search' with reference present
     */
    public function testWithReference() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'reference' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results['kjv']);
    }

    /**
     * Request is mapped to 'reference' with search present
     */
    public function testWithSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'request' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results['kjv']);
    }

    /**
     * This will return an error
     */
    public function testWithPassageAndSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'request' => 'Romans', 'reference' => 'Acts', 'page_all' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
    }

    /**
     * 'Romans 1' will be recognized as a reference
     * 'Romans, John' will be recognized as a reference
     */
    public function testAsReference() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans 1', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(32, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans,John', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(83, $results['kjv']);
    }

    /**
     * A passage typed in the interface language, against a Bible of another language, must
     * resolve - including book names that contain parentheses and ranges using any unicode
     * dash. Previously the minus sign (U+2212) leaked into the search SQL and crashed the
     * query (BSS-265/270).
     */
    public function testForeignLanguagePassageAgainstEnglishBible()
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $book = 'Pirmā Mozus grāmata (Genesis)'; // Latvian for Genesis

        foreach (['-', "\u{2013}", "\u{2014}", "\u{2015}", "\u{2212}"] as $dash) {
            foreach (['request', 'reference'] as $field) {
                $input = [
                    'bible'       => 'kjv',
                    'language'    => 'lv',
                    'page_all'    => TRUE,
                    'whole_words' => FALSE,
                    $field        => $book . ' 1:1' . $dash . '2',
                ];

                $results = $Engine->actionQuery($input);
                $errors  = $Engine->getErrors();

                $this->assertFalse($Engine->hasErrors(), "Errors for $field with dash U+" . bin2hex($dash) . ': ' . implode(' | ', $errors));
                $this->assertCount(2, $results['kjv'], "Expected Genesis 1:1-2 for $field");
            }
        }
    }

    /**
     * A reused Engine instance must not carry a prior query's interface language into a later
     * query that supplies no language. setBibles() appends default_language as a book-name
     * fallback, so a stale value would resolve foreign book names the new request never asked
     * for (BSS-265/270).
     */
    public function testReusedEngineDoesNotCarryStaleInterfaceLanguage()
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $book = 'Pirmā Mozus grāmata (Genesis)'; // Latvian for Genesis

        // Query 1: Latvian interface language resolves the Latvian book name against the English Bible.
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'language'    => 'lv',
            'page_all'    => TRUE,
            'whole_words' => FALSE,
            'request'     => $book . ' 1:1',
        ]);

        $this->assertFalse($Engine->hasErrors(), implode(' | ', $Engine->getErrors()));
        $this->assertCount(1, $results['kjv']);

        // Query 2 on the SAME instance, no language. Without the stale 'lv' fallback the Latvian
        // book name no longer resolves, so it is treated as a (non-matching) search, not Genesis.
        $results = $Engine->actionQuery([
            'bible'       => 'kjv',
            'page_all'    => TRUE,
            'whole_words' => FALSE,
            'request'     => $book . ' 1:1',
        ]);

        $this->assertEmpty($results['kjv'], 'Reused Engine should not resolve the Latvian book name without a language');
    }

    public function testWithBooleanProximity()
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith PROX(2) hope', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
    }

    /**
     * 'faith' will be recognized as a search
     * 'Romans' will be recognized as a search, not a reference
     */
    public function testAsSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(231, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(6, $results['kjv']); // 7 if module has Pauline postscripts

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Peter John', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(19, $results['kjv']);
    }    

    public function testCrossReferencesAreAggregatedAcrossReturnedVerses(): void
    {
        if(!Feature::isEnabled('cross_references')) {
            $this->markTestSkipped('Cross references feature not installed or enabled');
        }
    
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');

        $singleBibleResults = $Engine->actionQuery([
            'bible' => 'kjv',
            'reference' => 'John 3:16',
            'page_all' => TRUE,
            'cross_references' => TRUE,
        ]);

        $singleBibleMetadata = $Engine->getMetadata();
        $crossReferences = array_values($singleBibleMetadata->cross_references);

        $this->assertFalse($Engine->hasErrors());
        $this->assertIsArray($crossReferences);
        $this->assertNotEmpty($crossReferences, 'Expected cross references to be present in metadata');
        $this->assertArrayHasKey('from_book', $crossReferences[0]);
        $this->assertArrayHasKey('cross_references', $crossReferences[0]);
        $this->assertArrayNotHasKey('created_at', $crossReferences[0]);
        $this->assertArrayNotHasKey('updated_at', $crossReferences[0]);
        $this->assertArrayNotHasKey('created_at', $crossReferences[0]['cross_references'][0]);
        $this->assertArrayNotHasKey('updated_at', $crossReferences[0]['cross_references'][0]);

        if(!Bible::isEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        $multiBibleResults = $Engine->actionQuery([
            'bible' => ['kjv', 'bishops'],
            'reference' => 'John 3:16',
            'page_all' => TRUE,
            'cross_references' => TRUE,
        ]);

        $this->assertFalse($Engine->hasErrors());
        $multiBibleMetadata = $Engine->getMetadata();
        $mbCrossReferences = array_values($multiBibleMetadata->cross_references);

        $this->assertCount(1, $singleBibleResults['kjv']);
        $this->assertCount(2, $multiBibleResults);
        $this->assertCount(count($crossReferences), $mbCrossReferences);
        $this->assertSame(
            [$crossReferences[0]['from_book'], $crossReferences[0]['from_chapter'], $crossReferences[0]['from_verse']],
            [$mbCrossReferences[0]['from_book'], $mbCrossReferences[0]['from_chapter'], $mbCrossReferences[0]['from_verse']]
        );
    }

    // In this case, the request and reference fields are both references.
    // The code will look at the request field and ignore the reference field.
    public function testWithTwoReferences() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Revelation 1:1', 'reference' => 'Romans', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);        

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Revelation 1:1', 'reference' => 'Romans 1', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['kjv']);
        $this->assertEquals(66, $results['kjv'][0]->book);
    }

    public function testWithTwoSearches() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'faith', 'search' => 'hope', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertTrue($Engine->hasErrors());
    }

    public function testAsRegexpSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'love.{0,200}joy', 'whole_words' => TRUE, 'page_all' => TRUE, 'search_type' => 'regexp']);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testDisambiguation() 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Romans']);
        $this->assertFalse($Engine->hasErrors());
        $metadata = $Engine->getMetadata();

        $this->assertCount(1, $metadata->disambiguation);
        $this->assertEquals('Romans', $metadata->disambiguation[0]['simple']);

        // No longer treated as a disambuation situation (BSS-104)
        // $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'kings']);
        // $this->assertFalse($Engine->hasErrors());
        // $metadata = $Engine->getMetadata();

        // $this->assertCount(2, $metadata->disambiguation);
        // $this->assertEquals('1 Kings', $metadata->disambiguation[0]['simple']);
        // $this->assertEquals('2 Kings', $metadata->disambiguation[1]['simple']);

        // This should be a keyword search, with NO disambiguation
        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'Eve', 'whole_words' => true]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertEquals(1, $results['kjv'][0]->book);
        $this->assertEquals(3, $results['kjv'][0]->chapter);
        $this->assertEquals(20, $results['kjv'][0]->verse);

        $metadata = $Engine->getMetadata();
        $this->assertCount(0, $metadata->disambiguation);
    }

    public function testDisambiguationWithParenthesizedBookName()
    {
        // Latvian book names containing parentheses must produce a disambiguation link
        $result = Passage::mapRequest(
            ['request' => 'Pirmā Mozus grāmata (Genesis)', 'bible' => 'test'],
            ['lv'],
            []
        );

        $this->assertEquals('Pirmā Mozus grāmata (Genesis)', $result[0]); // treated as keywords
        $this->assertNull($result[1]);                                      // not a reference
        $this->assertTrue($result[3]);                                      // has disambiguation book
        $this->assertCount(1, $result[2]);
        $this->assertEquals('Pirmā Mozus grāmata (Genesis)', $result[2][0]['simple']);

        // Check LT if the table exists, but don't fail if it doesn't since this is just a test of the disambiguation behavior
        if (Schema::hasTable('books_lt')) {
            // Lithuanian book with parentheses
            $result = Passage::mapRequest(
                ['request' => 'Kunigų (Levitų)', 'bible' => 'test'],
                ['lt'],
                []
            );

            $this->assertTrue($result[3]);
            $this->assertCount(1, $result[2]);
            $this->assertEquals('Kunigų (Levitų)', $result[2][0]['simple']);
        }
        // Search queries with parentheses must NOT produce a disambiguation
        $result = Passage::mapRequest(
            ['request' => 'love (God)', 'bible' => 'kjv'],
            ['en'],
            []
        );
        $this->assertFalse($result[3]);
    }

    public function testDisambiguationWithForeignBookName()
    {
        // A single foreign-language book name in the request field must produce a
        // disambiguation link, the same as English (BSS-265). Uses mapRequest directly
        // so no installed Bible of that language is required.

        // Russian "Бытие" (Genesis) - data is stored lower-case, user types it capitalized
        $result = Passage::mapRequest(['request' => 'Бытие', 'bible' => 'test'], ['ru'], []);
        $this->assertTrue($result[3]);
        $this->assertCount(1, $result[2]);
        $this->assertEquals('бытие', $result[2][0]['simple']);

        // Spanish "Génesis" (and accent-free spelling) must also disambiguate
        foreach (['Génesis', 'Genesis'] as $request) {
            $result = Passage::mapRequest(['request' => $request, 'bible' => 'test'], ['es'], []);
            $this->assertTrue($result[3], "Expected disambiguation for '$request'");
            $this->assertCount(1, $result[2]);
            $this->assertEquals('Génesis', $result[2][0]['simple']);
        }
    }

    public function testForeignBookPassageWithParenthesesIsTreatedAsReference()
    {
        // Book names in some languages contain parentheses, e.g. Latvian
        // "Pirmā Mozus grāmata (Genesis)". A passage using such a name in the request field
        // must be routed to the reference (not misclassified as a boolean/keyword search just
        // because it contains parentheses), for every kind of range dash (BSS-265/270).
        $book = 'Pirmā Mozus grāmata (Genesis)';

        $dashes = [
            'hyphen'    => '-',
            'en dash'   => "\u{2013}",
            'em dash'   => "\u{2014}",
            'horiz bar' => "\u{2015}",
            'minus'     => "\u{2212}",
        ];

        foreach ($dashes as $label => $dash) {
            $request = $book . ' 1:1' . $dash . '2';
            $result = Passage::mapRequest(['request' => $request, 'bible' => 'test'], ['lv'], []);

            $this->assertNull($result[0], "Expected no keywords for $label range");
            // Range dashes are normalized to '-' on the reference.
            $this->assertEquals($book . ' 1:1-2', $result[1], "Expected reference for $label range");
        }
    }

    public function testRequestKeywordsPreserveUnicodeDashes()
    {
        // When the request field is free-text search (not a passage), unicode dashes must
        // NOT be normalized to '-' the way they are for references/ranges, otherwise phrase
        // searches break (cf. UnicodeTest::testLatvian). The request field can become either
        // a reference or keywords, so both keyword routes must preserve the original dashes.

        // Request-only, classified as keywords (no numbers, has an em-dash)
        $emdash = "love \u{2014} joy"; // em-dash
        $result = Passage::mapRequest(['request' => $emdash, 'bible' => 'test'], ['en'], []);
        $this->assertEquals($emdash, $result[0]); // keywords preserved verbatim
        $this->assertNull($result[1]);            // not treated as a reference

        // Request alongside an existing reference, request is not a strict passage -> keywords
        $endash = "mercy \u{2013} grace"; // en-dash
        $result = Passage::mapRequest(
            ['request' => $endash, 'reference' => 'John 3:16', 'bible' => 'test'],
            ['en'],
            []
        );
        $this->assertEquals($endash, $result[0]); // keywords preserved verbatim
    }

    public function testDisambiguationWithPassageLimit()
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'request' => 'mark', 'reference' => 'Revelation', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(8, $results['kjv']);
    }

    public function testNonPassageCharacters() 
    {
        $this->assertFalse( Passage::_containsNonPassageCharacters('Romans 1') );
        $this->assertFalse( Passage::_containsNonPassageCharacters('Romanos') );
        $this->assertFalse( Passage::_containsNonPassageCharacters('Ésaïe 31') );
        $this->assertTrue( Passage::_containsNonPassageCharacters('love.*joy') );
        $this->assertTrue( Passage::_containsNonPassageCharacters('love.{0,200}joy') );
        $this->assertTrue( Passage::_containsNonPassageCharacters('(love OR joy ) hope') );
    }

    public function testSearchGroupedAsPassage() 
    {
        $Engine = Engine::getInstance();

        $query = [
            'bible'         => 'kjv', 
            'request'       => 'faith', 
            'reference'     => 'Ps 89', 
            'whole_words'   => false,
            'data_format'   => 'lite',
        ];

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        // Returns 7 passages, containing 1 verse each
        $this->assertCount(7, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);

        $query['group_passage_search_results'] = true;

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        // Returns 1 passages, containing 7 verses
        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['single_verse']);
        $this->assertEquals(7, $results[0]['verses_count']);
    }    

    public function testSearchGroupedAsPassageMultiDifferentChapters() 
    {
        $Engine = Engine::getInstance();

        $query = [
            'bible'         => 'kjv', 
            'request'       => 'faith', 
            'reference'     => '1 Sam', 
            'whole_words'   => false,
            'data_format'   => 'lite',
        ];

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);

        // Since all of these results are in different chapters, 
        // turning on the passage grouping should have no effect on results

        $query['group_passage_search_results'] = true;

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(3, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);
    }    

    public function testSearchGroupedAsPassageMultiSharedChapters() 
    {
        $Engine = Engine::getInstance();

        $query = [
            'bible'         => 'kjv', 
            'request'       => 'faith', 
            'reference'     => 'Romans', 
            'whole_words'   => false,
            'data_format'   => 'lite',
            'page_all'      => true,
        ];

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(34, $results);
        $this->assertTrue($results[0]['single_verse']);
        $this->assertEquals(1, $results[0]['verses_count']);

        // Since all of these results are in different chapters, 
        // turning on the passage grouping should have no effect on results

        $query['group_passage_search_results'] = true;

        $results = $Engine->actionQuery($query);

        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results);  // 10 different chapters returned
        $this->assertFalse($results[0]['single_verse']);
        $this->assertEquals(4, $results[0]['verses_count']);
    }
}
