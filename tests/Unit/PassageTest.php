<?php

namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Passage;

class PassageTest extends TestCase
{
    public function testCanBeInstantiated(): void
    {
        $passage = new Passage();
        $this->assertInstanceOf(Passage::class, $passage);
    }

    public function testEmptyReference()
    {
        $empty = array('', NULL, FALSE, []);

        foreach($empty as $val) {
            $Passages = Passage::parseReferences($val);
            $this->assertFalse($Passages);
        }
    }
    
    public function testPassageRegexp(): void
    {
        $pattern = Passage::PASSAGE_REGEXP;
        $this->assertNotEmpty($pattern);
    }
    
    #[DataProvider('passageRegexpDataProvider')]
    public function testPassageRegexpAgainstVerse(string $text, array $passage, array $book, array $cv): void
    {
        $pattern = Passage::PASSAGE_REGEXP;

        $res = preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
        
        // Make sure the REGEX didn't have an error.
        $this->assertNotFalse($res);

        // Make sure we found all the passages we were expecting
        $this->assertEquals(count($passage), $res, $text);

        foreach ($passage as $key => $p) {
            $this->assertEquals($p, $matches[$key][0]);
        }

        foreach($book as $key => $p) {
            $this->assertEquals($p, trim($matches[$key][1]));
        }            

        foreach($cv as $key => $p) {
            $this->assertEquals($p, trim($matches[$key][4]));
        }
    }

    public static function passageRegexpDataProvider(): array
    {
        return [
            [
                'text'      => '<tag>Mark 16</tag>',
                'passage'   => ['Mark 16'],
                'book'      => ['Mark'],
                'cv'        => ['16'],
            ],                    
            [
                'text'      => '<tag>MARK 16</tag>',
                'passage'   => ['MARK 16'],
                'book'      => ['MARK'],
                'cv'        => ['16'],
            ],               
            [
                'text'      => '<div>2 Cor 13</div>',
                'passage'   => ['2 Cor 13'],
                'book'      => ['2 Cor'],
                'cv'        => ['13'],
            ],                
            [
                'text'      => '<div>1Cor 3-5</div>',
                'passage'   => ['1Cor 3-5'],
                'book'      => ['1Cor'],
                'cv'        => ['3-5'],
            ],                 
            [
                'text'      => '<div>2Cor4-7</div>',
                'passage'   => ['2Cor4-7'],
                'book'      => ['2Cor'],
                'cv'        => ['4-7'],
            ],            
            [
                'text'      => '<div>1 Cor 3 - 5</div>',
                'passage'   => ['1 Cor 3 - 5'],
                'book'      => ['1 Cor'],
                'cv'        => ['3 - 5'],
            ],              
            [
                'text'      => '<div>Song of Solomon 2</div>Stuff',
                'passage'   => ['Song of Solomon 2'],
                'book'      => ['Song of Solomon'],
                'cv'        => ['2'],
            ],            
            [
                'text'      => '<span>Jn 3:16</span><p>Here is the truth</p>',
                'passage'   => ['Jn 3:16'],
                'book'      => ['Jn'],
                'cv'        => ['3:16'],
            ],             
            [
                'text'      => '<span>Rev. 3:10</span><p>Test</p>',
                'passage'   => ['Rev. 3:10'],
                'book'      => ['Rev.'],
                'cv'        => ['3:10'],
            ],            
            [
                'text'      => '<b>Ommitted</b><tag>1 Jn 5:7</tag>',
                'passage'   => ['1 Jn 5:7'],
                'book'      => ['1 Jn'],
                'cv'        => ['5:7'],
            ],
            [
                'text'      => '<span>Rom 3:9-15</span>',
                'passage'   => ['Rom 3:9-15'],
                'book'      => ['Rom'],
                'cv'        => ['3:9-15'],
            ],            
            [
                'text'      => '<span>Jn 5:2,17</span>',
                'passage'   => ['Jn 5:2,17'],
                'book'      => ['Jn'],
                'cv'        => ['5:2,17'],
            ],            
            [
                'text'      => '<h1>Main Header</h1><h2>Exo 20:1,3,13, 17</h2><div>Big container div</div>',
                'passage'   => ['Exo 20:1,3,13, 17'],
                'book'      => ['Exo'],
                'cv'        => ['20:1,3,13, 17'],
            ],
            [
                'text'      => '<p>2 Cor 5:1-10, 6:12, 16, 12:2</p>',
                'passage'   => ['2 Cor 5:1-10, 6:12, 16, 12:2'],
                'book'      => ['2 Cor'],
                'cv'        => ['5:1-10, 6:12, 16, 12:2'],
            ],            
            [
                'text'      => '<p>2 Thess 2:8-10, 16, Rev 5:1-11/p>',
                'passage'   => ['2 Thess 2:8-10, 16', 'Rev 5:1-11'],
                'book'      => ['2 Thess'],
                'cv'        => ['2:8-10, 16', '5:1-11'],
            ],            
            [
                'text'      => '<p>  2   Cor 2 : 8 - 10,   19, Rom  5 :  1   - 11/p>',
                'passage'   => ['2   Cor 2 : 8 - 10,   19', 'Rom  5 :  1   - 11'],
                'book'      => ['2   Cor', 'Rom'],
                'cv'        => ['2 : 8 - 10,   19', '5 :  1   - 11'],
            ],            
            [
                'text'      => '<p>Jas 3:1 - 10, It was so unbearable. Acts  5:1- 11</p> Now think about this: Rom 10:9,10',
                'passage'   => ['Jas 3:1 - 10', 'Acts  5:1- 11', 'Rom 10:9,10'],
                'book'      => ['Jas', 'Acts', 'Rom'],
                'cv'        => ['3:1 - 10', '5:1- 11', '10:9,10'],
            ],
            [
                'text'      => '<span>Gen 50:23-</span>',
                'passage'   => ['Gen 50:23'],   // Note: does not match
                'book'      => ['Gen'],
                'cv'        => ['50:23'],
            ],              
            [
                'text'      => '<span>Gen 50:-23</span>',
                'passage'   => ['Gen 50'],      // Note: does not match
                'book'      => ['Gen'],
                'cv'        => ['50'],
            ],             
            [
                'text'      => '<span>The time was 1:30 PM</span>',
                'passage'   => ['The time was 1:30'],      // Note: Not a valid reference, but will match anyway
                'book'      => ['The time was'],
                'cv'        => ['1:30'],
            ],  
        ];
    }

    public function testIsAlphaReturnsTrueForAlpha(): void
    {
        $this->assertTrue(Passage::isAlpha('A'));
        $this->assertTrue(Passage::isAlpha('α'));
    }

    public function testIsAlphaReturnsFalseForNonAlpha(): void
    {
        $this->assertFalse(Passage::isAlpha('1'));
        $this->assertFalse(Passage::isAlpha('!'));
    }

    public function testIsChapterVerse(): void
    {
        $this->assertTrue(Passage::isChapterVerse('1'));
        $this->assertTrue(Passage::isChapterVerse(':'));
        $this->assertFalse(Passage::isChapterVerse('a'));
    }

    public function testIsWhitespace(): void
    {
        $this->assertTrue(Passage::isWhitespace(' '));
        $this->assertFalse(Passage::isWhitespace('a'));
    }

    public function testIsRandom(): void
    {
        $this->assertTrue(Passage::isRandom('random_book'));
        $this->assertTrue(Passage::isRandom('random chapter'));
        $this->assertFalse(Passage::isRandom('genesis'));
    }

    public function testNormalizeRandom(): void
    {
        $this->assertEquals('random_book', Passage::normalizeRandom('Random Book'));
        $this->assertEquals('random_chapter', Passage::normalizeRandom('random chapter'));
    }

    public function testIsPossiblePassage(): void
    {
        $this->assertTrue(Passage::isPossiblePassage('John 3:16'));
        $this->assertTrue(Passage::isPossiblePassage('Genesis 1'));
        $this->assertFalse(Passage::isPossiblePassage('!@#'));
    }

    public function testContainsNonPassageCharacters(): void
    {
        $this->assertTrue(Passage::_containsNonPassageCharacters('Gen 1:1!'));
        $this->assertFalse(Passage::_containsNonPassageCharacters('Gen 1:1'));
    }
}