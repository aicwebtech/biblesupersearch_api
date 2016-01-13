<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Passage;

class PassageTest extends TestCase
{
    public function testInstantiation() {
        $Passage = new Passage();
        $this->assertInstanceOf('App\Passage', $Passage);
    }
    
    public function testSingleVerseParse() {
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
    }
    
    public function testWholeChapterParse() {
        $reference = 'Romans 1; Acts 3 - 4, John 2,5';
        $Passages = Passage::parseReferences($reference, ['en']);
        $this->assertCount(3, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        // Test Books
        $this->assertEquals('Romans', $Passages[0]->Book->name);
        $this->assertEquals('Acts', $Passages[1]->Book->name);
        $this->assertEquals('John', $Passages[2]->Book->name);
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
    }
    
    public function testWholeChapterComplexParse() {
        $reference = 'Jas 1, 4-5, 1 Cor 2, 5-7, 9, 12';
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
    }

    public function testWholeChapterWeirdParse() {
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
    }
    
    public function testAbbreviatedParse() {
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
    }
    
    public function testMultipleVerses() {
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
        //$this->assertEquals($parsed[1], $Passages[1]->chapter_verse_parsed);
        $this->assertEquals($parsed[2], $Passages[2]->chapter_verse_parsed);
        //$this->assertEquals($parsed[3], $Passages[3]->chapter_verse_parsed);
        //$this->assertEquals($parsed[4], $Passages[4]->chapter_verse_parsed);
    }
    
    public function testInvalidReferences() {
        $reference = '  Habrews 4:8; 1 Tom 3:1-5, 9 ';
        $Passages  = Passage::parseReferences($reference, ['en']);
        $this->assertCount(2, $Passages);
        $this->assertContainsOnlyInstancesOf('App\Passage', $Passages);
        $this->assertFalse($Passages[0]->is_valid);
        $this->assertFalse($Passages[1]->is_valid);
    }
    
    public function testBookRange() {
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
    
    public function testBookRangeWithoutSearch() {
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
        $this->assertContains('multiple', $errors[0]);
    }
    
    public function testShortcutReference() {
        
    }
    
    public function testShortcutReferenceWithoutSearch() {
        
    }
}
