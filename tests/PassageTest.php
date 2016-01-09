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
    }
    
    public function testInvalidReferences() {
        
    }
}
