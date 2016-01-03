<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Books\BookAbstract As Book;

class BookTest extends TestCase
{
    public function testBookFind() {
        $queries = ['Rom', 'Rev', 'Matthew', 'Jn', 'Jdg'];
        
        foreach($queries as $q) {
            $Book = Book::findByEnteredName($q);
            $this->assertInstanceOf('App\Models\Books\En', $Book);
        }
    }
    
    public function testMethodFindByEnteredName() {
        $Book = Book::findByEnteredName('Rom', 'en'); // Specified language
        $this->assertInstanceOf('App\Models\Books\En', $Book);
        $Book = Book::findByEnteredName('Rom');       // Default language
        $this->assertInstanceOf('App\Models\Books\En', $Book);
        
        $es_class = Book::getClassNameByLanguage('es');
        $Book = $es_class::findByEnteredName('Rom'); // Language based on class // Romanos (Romans in Spanish)
        $this->assertInstanceOf('App\Models\Books\Es', $Book);
        
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
        
        // No match
        $Book = Book::findByEnteredName('Jdgs'); // Looking for 'Judges' but won't match 
        $this->assertNull($Book);
    }
    
    
}
