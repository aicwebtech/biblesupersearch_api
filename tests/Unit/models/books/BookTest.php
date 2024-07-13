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

    public function testBookFindClassName() {
        $Book = Book::findByEnteredName('Rom', 'en'); // Specified language
        $this->assertInstanceOf('App\Models\Books\En', $Book);
        $Book = Book::findByEnteredName('Rom');       // Default language
        $this->assertInstanceOf('App\Models\Books\En', $Book);

        $es_class = Book::getClassNameByLanguage('es');
        $Book = $es_class::findByEnteredName('Rom'); // Language based on class // Romanos (Romans in Spanish)
        $this->assertInstanceOf('App\Models\Books\Es', $Book);
    }

    public function testBookListImportCSV()
    {
        $test_language = 'test'; // NOT a valid language code!
        Book::dropBookTable($test_language);

        $class_name = Book::getClassNameByLanguageRaw($test_language);
        $this->assertEquals('App\Models\Books\Test', $class_name);
        $this->assertFalse(class_exists($class_name));
        
        Book::makeClassByLanguage($test_language);

        // Table doesn't exist, so class still won't exist
        $this->assertFalse(class_exists($class_name));

        $this->assertTrue(Book::createBookTable($test_language));

        Book::makeClassByLanguage($test_language);

        // Table exist, so class still will exist noew
        $this->assertTrue(class_exists($class_name));

        // Test actual import
        Book::migrateFromCsv($test_language);
        $this->assertEquals(66, $class_name::count());


        // Drop table before exiting
        // Book::dropBookTable($test_language);
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
        $Book = Book::findByEnteredName('1 Pt', null, false, true); // 1 Peter
        $this->assertEquals(60, $Book->id);
        $Book = Book::findByEnteredName('2Pt', null, false, true); // 2 Peter
        $this->assertEquals(61, $Book->id);
        $Book = Book::findByEnteredName('1John', null, false, true); // 1 John
        $this->assertEquals(62, $Book->id);
        $Book = Book::findByEnteredName('II Sam', null, false, true); // 2 Samuel
        $this->assertEquals(10, $Book->id);
        $Book = Book::findByEnteredName('1st Sam', null, false, true); // 1 Samuel
        $this->assertEquals(9, $Book->id);
        $Book = Book::findByEnteredName('First Sam', null, false, true); // 1 Samuel
        $this->assertEquals(9, $Book->id);
        $Book = Book::findByEnteredName('Third John', null, false, true);
        $this->assertEquals(64, $Book->id);
        $Book = Book::findByEnteredName('III John', null, false, true);
        $this->assertEquals(64, $Book->id);
        $Book = Book::findByEnteredName('II Corin', null, false, true);
        $this->assertEquals(47, $Book->id);
        $Book = Book::findByEnteredName('2nd Pet', null, false, true);
        $this->assertEquals(61, $Book->id);
        $Book = Book::findByEnteredName('2nd Pet', null, false, true);
        $this->assertEquals(61, $Book->id);

        // No match
        $Book = Book::findByEnteredName('Jdsd', null, false, true); // Looking for 'Judges' but won't match
        $this->assertNull($Book);
        $Book = Book::findByEnteredName('faith'); // Attempting to search for 'faith' from reference input - no match!
        $this->assertNull($Book);
    }

    public function testModelQuery() {
        $class = 'App\Models\Books\En';
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
