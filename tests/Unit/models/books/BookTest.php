<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use aicwebtech\BibleSuperSearch\Models\Books\BookAbstract As Book;

class BookTest extends TestCase
{
    public function testBookFind() {
        $queries = ['Rom', 'Rev', 'Matthew', 'Jn', 'Jdg'];

        foreach($queries as $q) {
            $Book = Book::findByEnteredName($q);
            $this->assertInstanceOf('aicwebtech\BibleSuperSearch\Models\Books\En', $Book);
        }
    }

    public function testBookFindClassName() {
        $Book = Book::findByEnteredName('Rom', 'en'); // Specified language
        $this->assertInstanceOf('aicwebtech\BibleSuperSearch\Models\Books\En', $Book);
        $Book = Book::findByEnteredName('Rom');       // Default language
        $this->assertInstanceOf('aicwebtech\BibleSuperSearch\Models\Books\En', $Book);

        $es_class = Book::getClassNameByLanguage('es');
        $Book = $es_class::findByEnteredName('Rom'); // Language based on class // Romanos (Romans in Spanish)
        $this->assertInstanceOf('aicwebtech\BibleSuperSearch\Models\Books\Es', $Book);
    }

    public function testMethodFindByEnteredName() {
        // Exact name
        $Book = Book::findByEnteredName('Matthew');
        $this->assertEquals(40, $Book->id);

        // Short name
        $Book = Book::findByEnteredName('SOS'); // Song of Solomon
        $this->assertEquals(22, $Book->id);

        // Beginning of name
        $Book = Book::findByEnteredName('Dan'); // Daniel
        $this->assertEquals(27, $Book->id);

        // Loose matching
        $Book = Book::findByEnteredName('1 Pt'); // 1 Peter
        $this->assertEquals(60, $Book->id);
        $Book = Book::findByEnteredName('2Pt'); // 2 Peter
        $this->assertEquals(61, $Book->id);
        $Book = Book::findByEnteredName('1John'); // 1 John
        $this->assertEquals(62, $Book->id);
        $Book = Book::findByEnteredName('II Sam'); // 2 Samuel
        $this->assertEquals(10, $Book->id);
        $Book = Book::findByEnteredName('1st Sam'); // 1 Samuel
        $this->assertEquals(9, $Book->id);
        $Book = Book::findByEnteredName('First Sam'); // 1 Samuel
        $this->assertEquals(9, $Book->id);
        $Book = Book::findByEnteredName('Third John');
        $this->assertEquals(64, $Book->id);
        $Book = Book::findByEnteredName('III John');
        $this->assertEquals(64, $Book->id);
        $Book = Book::findByEnteredName('II Corin');
        $this->assertEquals(47, $Book->id);
        $Book = Book::findByEnteredName('2nd Pet');
        $this->assertEquals(61, $Book->id);
        $Book = Book::findByEnteredName('2nd Pet');
        $this->assertEquals(61, $Book->id);

        // No match
        $Book = Book::findByEnteredName('Jdsd'); // Looking for 'Judges' but won't match
        $this->assertNull($Book);
        $Book = Book::findByEnteredName('faith'); // Attempting to search for 'faith' from reference input - no match!
        $this->assertNull($Book);
    }

    public function testModelQuery() {
        $class = 'aicwebtech\BibleSuperSearch\Models\Books\En';
        // Get multiple models
        $multiple = [1,2,3,4,5]; // Genesis, Exodus, Leviticus, Numbers, Deuteronomy
        $alpha = [5,2,1,3,4];    // Aphabetical: Deuteronomy, Exodus, Genesis, Leviticus, Numbers
        $Books = $class::find($multiple);
        $this->assertContainsOnlyInstancesOf($class, $Books->all());

        $Books = $class::whereIn('id', $multiple)->orderBy('name')->get();
        $this->assertContainsOnlyInstancesOf($class, $Books->all());

        foreach($Books as $key => $Book) {
            $this->assertEquals($alpha[$key], $Book->id);
        }

    }


}
