<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Passage;
use PHPUnit\Framework\Attributes\DataProvider;

class PassageTest extends TestCase
{

    public function testEmptyReference() 
    {
        $empty = array('', NULL, FALSE, []);

        foreach($empty as $val) {
            $Passages = Passage::parseReferences($val);
            $this->assertFalse($Passages);
        }
    }

    public function testSingleVerseParse() 
    {
        // Single verse, exact full name book reference
        $reference = 'Romans 1:1; Acts 2:38; 1 John 2:5; Song of Solomon 2:3';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(4, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('Romans', $Passages[0]->Book->name);
        $this->assertEquals('Acts', $Passages[1]->Book->name);
        $this->assertEquals('1 John', $Passages[2]->Book->name);
        $this->assertEquals('Song of Solomon', $Passages[3]->Book->name);
        // Test Chapter / Verse
        $this->assertEquals('1:1', $Passages[0]->chapter_verse);
        $this->assertEquals('2:38', $Passages[1]->chapter_verse);
        $this->assertEquals('2:5', $Passages[2]->chapter_verse);
        $this->assertEquals('2:3', $Passages[3]->chapter_verse);

        // Test parsed chapter / verse
        $this->assertEquals(array( array('c' => 1, 'v' => 1, 'type' => 'single'), ),  $Passages[0]->chapter_verse_parsed);
        $this->assertEquals(array( array('c' => 2, 'v' => 38, 'type' => 'single'), ), $Passages[1]->chapter_verse_parsed);
        $this->assertEquals(array( array('c' => 2, 'v' => 5, 'type' => 'single'), ),  $Passages[2]->chapter_verse_parsed);
        $this->assertEquals(array( array('c' => 2, 'v' => 3, 'type' => 'single'), ),  $Passages[3]->chapter_verse_parsed);

        // Test Min/Max chapter
        $this->assertEquals(1, $Passages[0]->chapter_min);
        $this->assertEquals(1, $Passages[0]->chapter_max);
        $this->assertEquals(2, $Passages[1]->chapter_min);
        $this->assertEquals(2, $Passages[1]->chapter_max);
        $this->assertEquals(2, $Passages[2]->chapter_min);
        $this->assertEquals(2, $Passages[2]->chapter_max);
        $this->assertEquals(2, $Passages[3]->chapter_min);
        $this->assertEquals(2, $Passages[3]->chapter_max);
    }

    public function testWholeChapterParse() 
    {
        $reference = 'Romans 1; Acts 3 - 4, John 2,5';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(3, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('Romans', $Passages[0]->Book->name);
        $this->assertEquals('Acts', $Passages[1]->Book->name);
        $this->assertEquals('John', $Passages[2]->Book->name);
        $this->assertFalse($Passages[0]->isSingleBook());
        $this->assertFalse($Passages[1]->isSingleBook());
        $this->assertFalse($Passages[2]->isSingleBook());
        // Test Chapter / Verse
        $this->assertEquals('1', $Passages[0]->chapter_verse);
        $this->assertEquals('3-4', $Passages[1]->chapter_verse);
        $this->assertEquals('2,5', $Passages[2]->chapter_verse);

        $expected = array(
            array( array('c' => 1, 'v' => NULL, 'type' => 'single'), ),
            array( array('cst' => 3, 'vst' => NULL, 'cen' => 4, 'ven' => NULL, 'type' => 'range'), ),
            array( array('c' => 2, 'v' => NULL, 'type' => 'single'), array('c' => 5, 'v' => NULL, 'type' => 'single'), ),
        );

        // Test parsed chapter / verse
        $this->assertEquals($expected[0], $Passages[0]->chapter_verse_parsed);
        $this->assertEquals($expected[1], $Passages[1]->chapter_verse_parsed);
        $this->assertEquals($expected[2], $Passages[2]->chapter_verse_parsed);

        // Test Min / Max chapters
        $this->assertEquals(1, $Passages[0]->chapter_min);
        $this->assertEquals(1, $Passages[0]->chapter_max);
        $this->assertEquals(3, $Passages[1]->chapter_min);
        $this->assertEquals(4, $Passages[1]->chapter_max);
        $this->assertEquals(2, $Passages[2]->chapter_min);
        $this->assertEquals(5, $Passages[2]->chapter_max);
    }

    public function testWholeBookParse() 
    {
        $reference = 'Romans; Acts, John';
        $Passages = Passage::parseReferences($reference, ['en'], TRUE);
        $this->assertCount(3, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('Romans', $Passages[0]->Book->name);
        $this->assertEquals('Acts', $Passages[1]->Book->name);
        $this->assertEquals('John', $Passages[2]->Book->name);

        // Test Chapter / Verse
        $this->assertEquals('', $Passages[0]->chapter_verse);
        $this->assertEquals('', $Passages[1]->chapter_verse);
        $this->assertEquals('', $Passages[2]->chapter_verse);

        // Test parsed chapter / verse
        $this->assertEquals(array(), $Passages[0]->chapter_verse_parsed);
        $this->assertEquals(array(), $Passages[1]->chapter_verse_parsed);
        $this->assertEquals(array(), $Passages[2]->chapter_verse_parsed);

        $this->assertTrue($Passages[0]->isSingleBook());
        $this->assertTrue($Passages[1]->isSingleBook());
        $this->assertTrue($Passages[2]->isSingleBook());

        // Test min / max chapters - shuld be null
        $this->assertNull($Passages[0]->chapter_min);
        $this->assertNull($Passages[0]->chapter_max);
        $this->assertNull($Passages[1]->chapter_min);
        $this->assertNull($Passages[1]->chapter_max);
        $this->assertNull($Passages[2]->chapter_min);
        $this->assertNull($Passages[2]->chapter_max);
    }

    public function testWholeChapterComplexParse() 
    {
        $reference = 'Jas. 1, 4-5, 1 Cor 2, 5-7, 9, 12';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(2, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('James', $Passages[0]->Book->name);
        $this->assertEquals('1 Corinthians', $Passages[1]->Book->name);
        // Test Chapter / Verse
        $this->assertEquals('1,4-5', $Passages[0]->chapter_verse);
        $this->assertEquals('2,5-7,9,12', $Passages[1]->chapter_verse);

        $expected_0 = array(
            array('c' => 1, 'v' => NULL, 'type' => 'single'),
            array('cst' => 4, 'vst' => NULL, 'cen' => 5, 'ven' => NULL, 'type' => 'range'),
        );

        $expected_1 = array(
            array('c' => 2, 'v' => NULL, 'type' => 'single'),
            array('cst' => 5, 'vst' => NULL, 'cen' => 7, 'ven' => NULL, 'type' => 'range'),
            array('c' => 9, 'v' => NULL, 'type' => 'single'),
            array('c' => 12, 'v' => NULL, 'type' => 'single'),
        );

        $this->assertEquals($expected_0, $Passages[0]->chapter_verse_parsed);
        $this->assertEquals($expected_1, $Passages[1]->chapter_verse_parsed);

        // Test Min / Max Chapters
        $this->assertEquals(1,  $Passages[0]->chapter_min);
        $this->assertEquals(5,  $Passages[0]->chapter_max);
        $this->assertEquals(2,  $Passages[1]->chapter_min);
        $this->assertEquals(12, $Passages[1]->chapter_max);
    }

    public function testWholeChapterWeirdParse() 
    {
        $reference = 'Hab 1-4-5,,7, Gen , 5-7-11';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(2, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('Habakkuk', $Passages[0]->Book->name);
        $this->assertEquals('Genesis', $Passages[1]->Book->name);
        // Test Chapter / Verse
        $this->assertEquals('1-4-5,7', $Passages[0]->chapter_verse);
        $this->assertEquals('5-7-11', $Passages[1]->chapter_verse);

        $expected_0 = array(
            array('cst' => 1, 'vst' => NULL, 'cen' => 5, 'ven' => NULL, 'type' => 'range'),
            array('c' => 7, 'v' => NULL, 'type' => 'single'),
        );

        $expected_1 = array(
            array('cst' => 5, 'vst' => NULL, 'cen' => 11, 'ven' => NULL, 'type' => 'range'),
        );

        $this->assertEquals($expected_0, $Passages[0]->chapter_verse_parsed);
        $this->assertEquals($expected_1, $Passages[1]->chapter_verse_parsed);

        $this->assertEquals(1, $Passages[0]->chapter_min);
        $this->assertEquals(7, $Passages[0]->chapter_max);
        $this->assertEquals(5, $Passages[1]->chapter_min);
        $this->assertEquals(11, $Passages[1]->chapter_max);
    }

    public function testAbbreviatedParse() 
    {
        //Varying up whitespace and punctuation
        $reference = ' Gen 1:5;2:3,   2 Cor 4:13; 3Jn. 1:5,    ';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(3, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('Genesis', $Passages[0]->Book->name);
        $this->assertEquals('2 Corinthians', $Passages[1]->Book->name);
        $this->assertEquals('3 John', $Passages[2]->Book->name);
        // Test Chapter / Verse
        $this->assertEquals('1:5,2:3', $Passages[0]->chapter_verse);
        $this->assertEquals('4:13', $Passages[1]->chapter_verse);
        $this->assertEquals('1:5', $Passages[2]->chapter_verse);
        // Test Raw Chapter / Verse
        $this->assertEquals('1:5;2:3', $Passages[0]->raw_chapter_verse);
        $this->assertEquals('4:13', $Passages[1]->raw_chapter_verse);
        $this->assertEquals('1:5', $Passages[2]->raw_chapter_verse);

        $parsed = array(
            0 => array(
                array('c' => 1, 'v' => 5, 'type' => 'single'),
                array('c' => 2, 'v' => 3, 'type' => 'single'),
            ),
            1 => array(
                array('c' => 4, 'v' => 13, 'type' => 'single'),
            ),
            2 => array(
                array('c' => 1, 'v' => 5, 'type' => 'single'),
            ),
        );

        $this->assertEquals($parsed[0], $Passages[0]->chapter_verse_parsed);
        $this->assertEquals($parsed[1], $Passages[1]->chapter_verse_parsed);
        $this->assertEquals($parsed[2], $Passages[2]->chapter_verse_parsed);
        $this->assertEquals(1, $Passages[0]->chapter_min);
        $this->assertEquals(2, $Passages[0]->chapter_max);
        $this->assertEquals(4, $Passages[1]->chapter_min);
        $this->assertEquals(4, $Passages[1]->chapter_max);
        $this->assertEquals(1, $Passages[2]->chapter_min);
        $this->assertEquals(1, $Passages[2]->chapter_max);
    }

    public function testBookNumberParse() 
    {
        $reference = '19 91:2-8';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertFalse($Passages);

        $reference = '19B 91:2-8';
        $references = Passage::explodeReferences($reference, TRUE);
        $this->assertTrue(is_array($references), 'References failed to parse');
        $this->assertEquals('19B', $references[0]['book']);
    }

    public function testMultipleVerses() 
    {
        // Really varying up format, punctuation, whitespace
        $reference = '  Rm 1:16 ;  1Thes 4:5- 6:3, 8:2-3, Tit 1:4, Rev 3:1-3;  4:  , Rom 3:23, 6:23; 5:8, 10:8  - 14    ';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(5, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('Romans', $Passages[0]->Book->name);
        $this->assertEquals('1 Thessalonians', $Passages[1]->Book->name);
        $this->assertEquals('Titus', $Passages[2]->Book->name);
        $this->assertEquals('Revelation', $Passages[3]->Book->name);
        $this->assertEquals('Romans', $Passages[4]->Book->name);
        // Test Chapter / Verse
        $this->assertEquals('1:16', $Passages[0]->chapter_verse);
        $this->assertEquals('4:5-6:3,8:2-3', $Passages[1]->chapter_verse);
        $this->assertEquals('1:4', $Passages[2]->chapter_verse);
        $this->assertEquals('3:1-3,4:', $Passages[3]->chapter_verse);
        $this->assertEquals('3:23,6:23,5:8,10:8-14', $Passages[4]->chapter_verse);
        // Test Raw Chapter / Verse
        $this->assertEquals('1:16', $Passages[0]->raw_chapter_verse);
        $this->assertEquals('4:5- 6:3, 8:2-3', $Passages[1]->raw_chapter_verse);
        $this->assertEquals('1:4', $Passages[2]->raw_chapter_verse);
        $this->assertEquals('3:1-3; 4:', $Passages[3]->raw_chapter_verse);
        $this->assertEquals('3:23, 6:23; 5:8, 10:8 - 14', $Passages[4]->raw_chapter_verse);

        $this->assertEquals(1,  $Passages[0]->chapter_min);
        $this->assertEquals(1,  $Passages[0]->chapter_max);
        $this->assertEquals(4,  $Passages[1]->chapter_min);
        $this->assertEquals(8,  $Passages[1]->chapter_max);
        $this->assertEquals(1,  $Passages[2]->chapter_min);
        $this->assertEquals(1,  $Passages[2]->chapter_max);
        $this->assertEquals(3,  $Passages[3]->chapter_min);
        $this->assertEquals(4,  $Passages[3]->chapter_max);
        $this->assertEquals(3,  $Passages[4]->chapter_min);
        $this->assertEquals(10, $Passages[4]->chapter_max);

        $parsed = array(
            array(
                array('c' => 1, 'v' => 16, 'type' => 'single'),
            ),
            array(
                array('cst' => 4, 'vst' => 5, 'cen' => 6, 'ven' => 3, 'type' => 'range'),
                array('cst' => 8, 'vst' => 2, 'cen' => 8, 'ven' => 3, 'type' => 'range'),
            ),
            array(
                array('c' => 1, 'v' => 4, 'type' => 'single'),
            ),
            array(
                array('cst' => 3, 'vst' => 1, 'cen' => 3, 'ven' => 3, 'type' => 'range'),
                array('c' => 4, 'v' => NULL, 'type' => 'single'),
            ),
            array(
                array('c' => 3, 'v' => 23, 'type' => 'single'),
                array('c' => 6, 'v' => 23, 'type' => 'single'),
                array('c' => 5, 'v' => 8, 'type' => 'single'),
                array('cst' => 10, 'vst' => 8, 'cen' => 10, 'ven' => 14, 'type' => 'range'),
            ),
        );

        $this->assertEquals($parsed[0], $Passages[0]->chapter_verse_parsed);
        $this->assertEquals($parsed[1], $Passages[1]->chapter_verse_parsed);
        $this->assertEquals($parsed[2], $Passages[2]->chapter_verse_parsed);
        $this->assertEquals($parsed[3], $Passages[3]->chapter_verse_parsed);
        $this->assertEquals($parsed[4], $Passages[4]->chapter_verse_parsed);
    }

    public function testPassageChapterExplosion() 
    {
        $reference = 'Rev 3:1-3;  4:;1:5-2:  ; Rom 3:23, 6:23; 5:8, 10: - 14';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(2, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertEquals(1,  $Passages[0]->chapter_min);
        $this->assertEquals(4,  $Passages[0]->chapter_max);
        $this->assertEquals(3,  $Passages[1]->chapter_min);
        $this->assertEquals(10, $Passages[1]->chapter_max);

        $Exploded = $Passages[0]->explodePassage(FALSE, TRUE);
        $this->assertCount(4, $Exploded);
        $this->assertEquals('3:1-3', $Exploded[0]->chapter_verse);
        $this->assertEquals('4',     $Exploded[1]->chapter_verse);
        $this->assertEquals('1:5-',     $Exploded[2]->chapter_verse);
        $this->assertEquals('2',     $Exploded[3]->chapter_verse);
        $this->assertFalse($Exploded[0]->isSingleVerse());
        $this->assertFalse($Exploded[1]->isSingleVerse());

        $Exploded = $Passages[1]->explodePassage(FALSE, TRUE);
        $this->assertCount(4, $Exploded);
        $this->assertTrue($Exploded[0]->isSingleVerse());
        $this->assertTrue($Exploded[1]->isSingleVerse());
        $this->assertTrue($Exploded[2]->isSingleVerse());
        $this->assertFalse($Exploded[3]->isSingleVerse());
        $this->assertEquals('3:23', $Exploded[0]->chapter_verse);
        $this->assertEquals('6:23',     $Exploded[1]->chapter_verse);
        $this->assertEquals('5:8',     $Exploded[2]->chapter_verse);
        $this->assertEquals('10:-14',     $Exploded[3]->chapter_verse);
    }

    #[DataProvider('chapterVerseParsingDataProvider')]
    public function testChapterVerseParsing(string $ref, array $exp, int|null $c_min, int|null $c_max): void
    {
        $Passages = Passage::parseReferences($ref, ['en']);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertEquals($exp, $Passages[0]->chapter_verse_parsed);
        $this->assertEquals($c_min, $Passages[0]->chapter_min);
        $this->assertEquals($c_max, $Passages[0]->chapter_max);
    }

    public static function chapterVerseParsingDataProvider(): array
    {
        return array(
            array(
                'ref' => 'Genesis 2',
                'exp' => array( array('c' => 2, 'v' => NULL, 'type' => 'single') ),
                'c_min' => 2,
                'c_max' => 2,
            ),
            array(
                'ref' => 'Genesis 2:',
                'exp' => array( array('c' => 2, 'v' => NULL, 'type' => 'single') ),
                'c_min' => 2,
                'c_max' => 2,
            ),
            array(
                'ref' => 'Genesis 2:1',
                'exp' => array( array('c' => 2, 'v' => 1, 'type' => 'single') ),
                'c_min' => 2,
                'c_max' => 2,
            ),
            array(
                'ref' => 'Genesis 2:1-5',
                'exp' => array( array('cst' => 2, 'vst' => 1, 'cen' => 2, 'ven' => 5, 'type' => 'range') ),
                'c_min' => 2,
                'c_max' => 2,
            ),
            array(
                'ref' => 'Genesis 2:1,4',
                'exp' => array(
                        array('c' => 2, 'v' => 1, 'type' => 'single'),
                        array('c' => 2, 'v' => 4, 'type' => 'single'),
                    ),
                'c_min' => 2,
                'c_max' => 2,
            ),
            array(
                'ref' => 'Genesis 2:1-3:4',
                'exp' => array( array('cst' => 2, 'vst' => 1, 'cen' => 3, 'ven' => 4, 'type' => 'range') ),
                'c_min' => 2,
                'c_max' => 3,
            ),
            array(
                'ref' => 'Genesis 2:-3:4',
                'exp' => array( array('cst' => 2, 'vst' => NULL, 'cen' => 3, 'ven' => 4, 'type' => 'range') ),
                'c_min' => 2,
                'c_max' => 3,
            ),
            array(
                'ref' => 'Genesis 2-3:4',
                'exp' => array( array('cst' => 2, 'vst' => NULL, 'cen' => 3, 'ven' => 4, 'type' => 'range') ),
                'c_min' => 2,
                'c_max' => 3,
            ),
            array(
                'ref' => 'Genesis 2:18-4:',
                'exp' => array( array('cst' => 2, 'vst' => 18, 'cen' => 4, 'ven' => NULL, 'type' => 'range') ),
                'c_min' => 2,
                'c_max' => 4,
            ),
            array(
                'ref' => 'Genesis 14,3:4',
                'exp' => array(
                        array('c' => 14, 'v' => NULL, 'type' => 'single'),
                        array('c' => 3, 'v' => 4, 'type' => 'single'),
                    ),
                'c_min' => 3,
                'c_max' => 14,
            ),
            array(
                'ref' => 'Genesis 14:,3:4',
                'exp' => array(
                        array('c' => 14, 'v' => NULL, 'type' => 'single'),
                        array('c' => 3, 'v' => 4, 'type' => 'single'),
                    ),
                'c_min' => 3,
                'c_max' => 14,
            ),
            array(
                'ref' => 'Rev 12:2 - :',
                'exp' => array(
                        array('cst' => 12, 'vst' => 2, 'cen' => NULL, 'ven' => NULL, 'type' => 'range'),
                    ),
                'c_min' => 12,
                'c_max' => 12,
            ),
            array(
                'ref' => 'Genesis 14-,3:4',
                'exp' => array(
                        array('cst' => 14, 'vst' => NULL, 'cen' => NULL, 'ven' => NULL, 'type' => 'range'),
                        //array('c' => 14, 'v' => NULL, 'type' => 'single'),
                        array('c' => 3, 'v' => 4, 'type' => 'single'),
                    ),
                'c_min' => 3,
                'c_max' => 14,
            ),
            array(
                'ref' => 'Genesis 3:4,14:',
                'exp' => array(
                        array('c' => 3, 'v' => 4, 'type' => 'single'),
                        array('c' => 14, 'v' => NULL, 'type' => 'single'),
                    ),
                'c_min' => 3,
                'c_max' => 14,
            ),
            array(
                'ref' => 'Genesis 3:4,14:-',
                'exp' => array(
                        array('c' => 3, 'v' => 4, 'type' => 'single'),
                        array('c' => 14, 'v' => NULL, 'type' => 'single'),
                    ),
                'c_min' => 3,
                'c_max' => 14,
            ),
            array(
                'ref' => 'Genesis 2:5 - 4:3, 7- 11',
                'exp' => array(
                        array('cst' => 2, 'vst' => 5, 'cen' => 4, 'ven' => 3, 'type' => 'range'),
                        array('cst' => 4, 'vst' => 7, 'cen' => 4, 'ven' => 11, 'type' => 'range'),
                    ),
                'c_min' => 2,
                'c_max' => 4,
            ),
            array(
                'ref' => 'Matt 25 - ',
                'exp' => array(
                        array('cst' => 25, 'vst' => NULL, 'cen' => NULL, 'ven' => NULL, 'type' => 'range'),
                    ),
                'c_min' => 25,
                'c_max' => 25,
            ),
            array(
                'ref' => 'Matt - 3',
                'exp' => array(
                        array('cst' => NULL, 'vst' => NULL, 'cen' => 3, 'ven' => NULL, 'type' => 'range'),
                    ),
                'c_min' => 3,
                'c_max' => 3,
            ),
            array(
                'ref' => 'Rev - 3:8',
                'exp' => array(
                        array('cst' => NULL, 'vst' => NULL, 'cen' => 3, 'ven' => 8, 'type' => 'range'),
                    ),
                'c_min' => NULL,
                'c_max' => 3,
            ),
            array(
                'ref' => 'Rev 12:2 - ',
                'exp' => array(
                        array('cst' => 12, 'vst' => 2, 'cen' => 12, 'ven' => NULL, 'type' => 'range'),
                    ),
                'c_min' => 12,
                'c_max' => 12,
            ),
            array(
                'ref' => '2 Cor 9:3 - 7',
                'exp' => array(
                        array('cst' => 9, 'vst' => 3, 'cen' => 9, 'ven' => 7, 'type' => 'range'),
                    ),
                'c_min' => 9,
                'c_max' => 9,
            ),
            array(
                'ref' => 'Romans 4:19 - 5',
                'exp' => array(
                        array('cst' => 4, 'vst' => 19, 'cen' => 5, 'ven' => NULL, 'type' => 'range'),
                    ),
                'c_min' => 4,
                'c_max' => 5,
            ),
            array(
                'ref' => 'Isa 5:16 - 7, 9:10 - 11, 15, 19; 25:19 - 28, 31:51 - 35',
                'exp' => array(
                        array('cst' => 5, 'vst' => 16, 'cen' => 7, 'ven' => NULL, 'type' => 'range'),
                        array('cst' => 9, 'vst' => 10, 'cen' => 9, 'ven' => 11, 'type' => 'range'),
                        array('c' => 9, 'v' => 15, 'type' => 'single'),
                        array('c' => 9, 'v' => 19, 'type' => 'single'),
                        array('cst' => 25, 'vst' => 19, 'cen' => 25, 'ven' => 28, 'type' => 'range'),
                        array('cst' => 31, 'vst' => 51, 'cen' => 35, 'ven' => NULL, 'type' => 'range'),
                    ),
                'c_min' => 5,
                'c_max' => 35,
            ),
            array(
                'ref' => 'Romans 4:19 - 4', // Invalid reference
                'exp' => array(
                        array('cst' => 4, 'vst' => 19, 'cen' => 4, 'ven' => 4, 'type' => 'range'),
                    ),
                'c_min' => 4,
                'c_max' => 4,
            ),
        );
    }

    public function testInvalidReferences() 
    {
        $reference = '  Habrews 4:8; 1 Tom 3:1-5, 9 ';
        $Passages  = Passage::parseReferences($reference, ['en']);
        $this->assertCount(2, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertFalse($Passages[0]->is_valid);
        $this->assertFalse($Passages[1]->is_valid);
        $this->assertTrue($Passages[0]->hasErrors());
        $this->assertTrue($Passages[1]->hasErrors());
        $errors = $Passages[0]->getErrors();
        $this->assertEquals(trans('errors.book.not_found', ['book' => 'Habrews']), $errors[0]);
        $errors = $Passages[1]->getErrors();
        $this->assertEquals(trans('errors.book.not_found', ['book' => '1 Tom']), $errors[0]);
    }

    public function testInvalidRangeReference() 
    {
        $reference = 'Ramans - Revelation';
        $Passages  = Passage::parseReferences($reference, ['en'], TRUE);
        $this->assertCount(1, $Passages);
        $this->assertFalse($Passages[0]->is_valid);
        $this->assertTrue($Passages[0]->hasErrors());
        $errors = $Passages[0]->getErrors();
        $this->assertEquals(trans('errors.book.invalid_in_range', ['range' => $reference]), $errors[0]);
    }

    public function testBookRange() 
    {
        $reference = 'Matthew - Revelation';
        $Passages = Passage::parseReferences($reference, ['en'], TRUE);
        $this->assertCount(1, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertTrue($Passages[0]->is_valid);
        $this->assertTrue($Passages[0]->is_book_range);
        $this->assertTrue($Passages[0]->is_search);
        $this->assertEquals(40, $Passages[0]->Book->id);
        $this->assertEquals(66, $Passages[0]->Book_En->id);
        $this->assertFalse($Passages[0]->hasErrors());
    }

    public function testBookRangeWithoutSearch() 
    {
        $reference = 'Matthew - Revelation';
        $Passages = Passage::parseReferences($reference, ['en'], FALSE);
        $this->assertCount(1, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertFalse($Passages[0]->is_search);
        $this->assertFalse($Passages[0]->is_valid);
        $this->assertNull($Passages[0]->Book);
        $this->assertNull($Passages[0]->Book_En);
        $this->assertTrue($Passages[0]->hasErrors());
        $errors = $Passages[0]->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(trans('errors.book.multiple_without_search'), $errors[0]);
    }

    public function testShortcutReferenceWithSearch() 
    {
        $is_search = TRUE;
        $nt_references = ['New Testament','NT','New'];

        foreach($nt_references as $reference) {
            $Passages = Passage::parseReferences($reference, ['en'], $is_search);
            $this->assertCount(1, $Passages);
            $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
            $this->assertTrue($Passages[0]->is_valid);
            $this->assertTrue($Passages[0]->is_book_range);
            $this->assertTrue($Passages[0]->is_search);
            $this->assertEquals(40, $Passages[0]->Book->id);
            $this->assertEquals(66, $Passages[0]->Book_En->id);
        }

        $end_times_references = ['End Times','Last Days','End Times Prophecy'];

        foreach($end_times_references as $reference) {
            $Passages = Passage::parseReferences($reference, ['en'], $is_search);
            $this->assertCount(3, $Passages);
            // Revelation
            $this->assertTrue($Passages[0]->is_valid);
            $this->assertFalse($Passages[0]->is_book_range);
            $this->assertTrue($Passages[0]->is_search);
            $this->assertEquals(66, $Passages[0]->Book->id);
            // Daniel
            $this->assertTrue($Passages[1]->is_valid);
            $this->assertFalse($Passages[1]->is_book_range);
            $this->assertTrue($Passages[1]->is_search);
            $this->assertEquals(27, $Passages[1]->Book->id);
            // Matthew 24
            $this->assertTrue($Passages[2]->is_valid);
            $this->assertFalse($Passages[2]->is_book_range);
            $this->assertTrue($Passages[2]->is_search);
            $this->assertEquals(40, $Passages[2]->Book->id);
            $this->assertEquals('24', $Passages[2]->raw_chapter_verse);
        }

        $Passages = Passage::parseReferences('NT;Psalms', ['en'], $is_search);
        $this->assertCount(2, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertTrue($Passages[0]->is_valid);
        $this->assertTrue($Passages[0]->is_book_range);
        $this->assertTrue($Passages[0]->is_search);
        $this->assertEquals(40, $Passages[0]->Book->id);
        $this->assertEquals(66, $Passages[0]->Book_En->id);
        $this->assertTrue($Passages[1]->is_valid);
        $this->assertFalse($Passages[1]->is_book_range);
        $this->assertTrue($Passages[1]->is_search);
        $this->assertEquals(19, $Passages[1]->Book->id);
    }

    public function testShortcutReferenceWithoutSearch() 
    {
        $is_search = FALSE;
        $nt_references = ['New Testament','NT','New'];

        foreach($nt_references as $reference) {
            $Passages = Passage::parseReferences($reference, ['en'], $is_search);
            $this->assertCount(1, $Passages);
            $this->assertFalse($Passages[0]->is_search);
            $this->assertFalse($Passages[0]->is_valid);
            $this->assertNull($Passages[0]->Book);
            $this->assertNull($Passages[0]->Book_En);
            $this->assertTrue($Passages[0]->hasErrors());
            $errors = $Passages[0]->getErrors();
            $this->assertCount(1, $errors);
            $this->assertStringContainsString('multiple', $errors[0]);
        }

        $end_times_references = ['End Times','Last Days','End Times Prophecy'];

        // As none of the 'End Times' references are ranges, they are all valid for reference lookup (only returns first chapter)
        foreach($end_times_references as $reference) {
            $Passages = Passage::parseReferences($reference, ['en'], $is_search);
            $this->assertCount(3, $Passages);
            $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
            // Revelation
            $this->assertFalse($Passages[0]->is_search);
            $this->assertTrue($Passages[0]->is_valid);
            // Daniel
            $this->assertFalse($Passages[1]->is_search);
            $this->assertTrue($Passages[1]->is_valid);
            // Matthew 24
            $this->assertFalse($Passages[2]->is_search);
            $this->assertTrue($Passages[2]->is_valid);
        }

        $Passages = Passage::parseReferences('NT;Psalms', ['en'], $is_search);
        $this->assertCount(2, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertFalse($Passages[0]->is_search);
        $this->assertFalse($Passages[0]->is_valid);
        $this->assertNull($Passages[0]->Book);
        $this->assertNull($Passages[0]->Book_En);
        $this->assertTrue($Passages[0]->hasErrors());
        $errors = $Passages[0]->getErrors();
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('multiple', $errors[0]);
        // The Psalms reference is still valid because it will just pull the first chapter
        $this->assertTrue($Passages[1]->is_valid);
        $this->assertFalse($Passages[1]->is_book_range);
        $this->assertFalse($Passages[1]->is_search);
        $this->assertEquals(19, $Passages[1]->Book->id);
    }

    function testParseRandomSearch() 
    {
        $is_search = FALSE;
        $ref = '1 John 1:1; Random Chapter, Random Verse, 2 Kings 1:1';

        $exploded = Passage::explodeReferences($ref, TRUE);
        $Passages = Passage::parseReferences($ref, ['en'], $is_search);

        $this->assertCount(4, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
    }

    function testIsPassage() 
    {
        $languages = ['en'];
        $this->assertTrue(Passage::isPassage('Romans', $languages));
        $this->assertTrue(Passage::isPassage('Romans 1', $languages));
        $this->assertTrue(Passage::isPassage('Romans 1:1', $languages));
        $this->assertFalse(Passage::isPassage('faith', $languages));
        $this->assertFalse(Passage::isPassage('faith hope', $languages));
        $this->assertFalse(Passage::isPassage('faith || hope', $languages));
    }
}