<?php

namespace Tests\Feature\Models;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Books\BookAbstract As Book;
use PHPUnit\Framework\Attributes\DataProvider;

class BookAbstractTest extends TestCase
{
    #[DataProvider('bookFindDataProvider')]
    public function testBookFind(string $book) 
    {
        $Book = Book::findByEnteredName($book);
        $this->assertInstanceOf('App\Models\Books\En', $Book);
    }

    public static function bookFindDataProvider()
    {
        return [
            ['Rom'],
            ['Rev'],
            ['Matthew'],
            ['Jn'],
            ['Jdg'],
        ];
    }

    public function testBookFindClassName() 
    {
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
        $test_language = 'art'; // Artifical language for testing
        Book::dropBookTable($test_language);

        $class_name = Book::getClassNameByLanguageRaw($test_language);
        $this->assertEquals('App\Models\Books\Art', $class_name);
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
        Book::dropBookTable($test_language);
    }

    public function testMethodByEnteredName() 
    {
        // Exact name
        $Book = Book::findByEnteredName('Matthew');
        $this->assertEquals(40, $Book->id);

        // Short name
        $Book = Book::findByEnteredName('SOS'); // Song of Solomon
        $this->assertEquals(22, $Book->id);

        // Beginning of name
        $Book = Book::findByEnteredName('Dan'); // Daniel
        $this->assertEquals(27, $Book->id);
    }

    #[DataProvider('bookLooseMatchDataProvider')]
    public function testFindByEnteredNameLooseMatch(string $book, int $id)
    {
        $Book = Book::findByEnteredName($book, null, false, true); // Loose matching
        $this->assertInstanceOf('App\Models\Books\En', $Book);
        $this->assertEquals($id, $Book->id);
    }

    public static function bookLooseMatchDataProvider()
    {
        return [
            ['1 Pt', 60],
            ['2Pt', 61],
            ['1John', 62],
            ['II Sam', 10],
            ['1st Sam', 9],
            ['First Sam', 9],
            ['Third John', 64],
            ['III John', 64],
            ['II Corin', 47],
            ['2nd Pet', 61],
        ];
    }

    public function testFindByEnderedNameNoMatch()
    {
        $Book = Book::findByEnteredName('Jdsd', null, false, true); // Looking for 'Judges' but won't match
        $this->assertNull($Book);
        $Book = Book::findByEnteredName('faith'); // Attempting to search for 'faith' from reference input - no match!
        $this->assertNull($Book);
    }

    public function testFindByEnteredNameWithParentheses()
    {
        // Latvian book names that contain parentheses must be found by exact match
        $Book = Book::findByEnteredName('Pirmā Mozus grāmata (Genesis)', 'lv');
        $this->assertNotNull($Book);
        $this->assertEquals(1, $Book->id);

        // Search queries with parentheses must NOT match any book
        $Book = Book::findByEnteredName('love (God)');
        $this->assertNull($Book);

        $Book = Book::findByEnteredName('(Gen OR Rev)');
        $this->assertNull($Book);
    }

    #[DataProvider('collationIndependentMatchDataProvider')]
    public function testFindByEnteredNameIsCollationIndependent(string $language, string $book, int $id)
    {
        // Foreign book names must be found regardless of the database collation, i.e.
        // case- and accent-insensitively, instead of falling back to the English list.
        $Book = Book::findByEnteredName($book, $language);
        $this->assertNotNull($Book, "Expected to find book for [$language] '$book'");
        $this->assertEquals($id, $Book->id);
    }

    public static function collationIndependentMatchDataProvider()
    {
        return [
            // Spanish - accent and case variations of "Génesis"
            'es exact'      => ['es', 'Génesis', 1],
            'es upper'      => ['es', 'GÉNESIS', 1],
            'es lower'      => ['es', 'génesis', 1],
            'es no accent'  => ['es', 'Genesis', 1],
            // Russian - case variations (data is stored lower-case)
            'ru exact'      => ['ru', 'бытие', 1],
            'ru title'      => ['ru', 'Бытие', 1],
            'ru upper'      => ['ru', 'БЫТИЕ', 1],
            // Latvian - parenthesized name, mixed case
            'lv lower'      => ['lv', 'pirmā mozus grāmata (genesis)', 1],
        ];
    }

    public function testModelQuery()
    {
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

    public function testIsSupportedLanguage()
    {
        // This will call \App\Models\Language::hasBookSupport, which should be mocked in a real test
        $this->assertIsBool(Book::isSupportedLanguage('en'));
    }
}
