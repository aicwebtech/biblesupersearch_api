<?php

namespace Tests\Feature;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Engine;
use App\Models\Bible;
use App\Models\Language;
use Illuminate\Support\Facades\Schema;

class EngineTest extends TestCase
{
    /** Connection name the broken-connection fixture below is wired to. */
    public const BROKEN_CONNECTION = 'engine_test_broken';

    public function testInstance() 
    {
        $engine = new Engine();
        $this->assertInstanceOf('App\Engine', $engine);
    }

    /**
     * Tests adding the default Bible on instantiation
     */
    public function testConfig() 
    {
        $engine = new Engine();
        $Bibles = $engine->getBibles();
        $this->assertCount(1, $Bibles);
        $this->assertContainsOnlyInstancesOf('App\Models\Bible', $Bibles);
    }

    public function testMethodAddBible() 
    {
        $engine = new Engine();
        $engine->addBible('kjv');
        $this->assertFalse($engine->hasErrors());
        $Bibles = $engine->getBibles();
        $this->assertInstanceOf('App\Models\Bible', $Bibles['kjv']);
    }

    public function testMethodSetBibles() 
    {
        $bibles = ['kjv', 'tr', 'tyndale', 'luther'];

        foreach($bibles as $key => $bible) {
            if(!Bible::isEnabled($bible)) {
                unset($bibles[$key]);
            }
        }

        if(empty($bibles)) {
            return;
        }

        $engine = new Engine();
        $engine->setBibles($bibles);
        $this->assertFalse($engine->hasErrors());
        $Bibles = $engine->getBibles();
        $this->assertCount(count($bibles), $Bibles);
    }

    public function testOtherBibles() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'kjv_strongs', 'search' => 'faith']);

        $modules = ['kjv_strongs', 'tyndale', 'bishops', 'coverdale'];

        foreach($modules as $module) {
            $Bible = Bible::findByModule($module);

            if(!$Bible->enabled) {
                continue;
            }

            $results = $Engine->actionQuery(['bible' => $module, 'search' => 'faith']);
            $this->assertFalse($Engine->hasErrors(), 'failed search on module: ' . $module);
            $this->assertTrue(count($results[$module]) > 0, 'empty results on module:' . $module);

            $results = $Engine->actionQuery(['bible' => $module, 'reference' => 'Rom']);
            $this->assertFalse($Engine->hasErrors(), 'failed lookup on module: ' . $module);
            $this->assertTrue(count($results[$module]) > 0, 'empty results on module:' . $module);
        }

        $this->assertTrue(TRUE);
    }

    public function testBasicSearch() 
    {
        // NOT whole word searches!
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith']);
        $this->assertCount(338, $results['kjv']);
        $this->assertEquals(4,  $results['kjv'][0]->book);
        $this->assertEquals(12, $results['kjv'][0]->chapter);
        $this->assertEquals(7,  $results['kjv'][0]->verse);
        $this->assertEquals('My servant Moses is not so, who is faithful in all mine house.',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(51, $results['kjv'][201]->book);
        $this->assertEquals(1,  $results['kjv'][201]->chapter);
        $this->assertEquals(4,  $results['kjv'][201]->verse);
        $this->assertEquals('Since we heard of your faith in Christ Jesus, and of the love which ye have to all the saints,',  $this->_tp($results['kjv'][201]->text));

        $results = $Engine->actionQuery(['bible' => 'kjv', 'reference' => 'Rom']);
        $this->assertCount(32, $results['kjv']);
        $this->assertEquals(45, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(1,  $results['kjv'][0]->verse);
        $this->assertEquals('Paul, a servant of Jesus Christ, called to be an apostle, separated unto the gospel of God,',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(45, $results['kjv'][29]->book);
        $this->assertEquals(1,  $results['kjv'][29]->chapter);
        $this->assertEquals(30, $results['kjv'][29]->verse);
        $this->assertEquals('Backbiters, haters of God, despiteful, proud, boasters, inventors of evil things, disobedient to parents,',  $this->_tp($results['kjv'][29]->text));

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'reference' => 'Rom']);
        $this->assertCount(34, $results['kjv']);
        $this->assertEquals(45, $results['kjv'][0]->book);
        $this->assertEquals(1,  $results['kjv'][0]->chapter);
        $this->assertEquals(5,  $results['kjv'][0]->verse);
        $this->assertEquals('By whom we have received grace and apostleship, for obedience to the faith among all nations, for his name:',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(45, $results['kjv'][30]->book);
        $this->assertEquals(14, $results['kjv'][30]->chapter);
        $this->assertEquals(1,  $results['kjv'][30]->verse);
        $this->assertEquals('Him that is weak in the faith receive ye, but not to doubtful disputations.',  $this->_tp($results['kjv'][30]->text));
    }

    public function testWholeWordSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'whole_words' => TRUE, 'exact_case' => FALSE]);
        $this->assertCount(231, $results['kjv']);
        $this->assertEquals(5,  $results['kjv'][0]->book);
        $this->assertEquals(32, $results['kjv'][0]->chapter);
        $this->assertEquals(20, $results['kjv'][0]->verse);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy', 'whole_words' => 'yes']);
        $this->assertCount(5, $results['kjv']);
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith joy', 'whole_words' => 'yes', 'search_type' => 'or']);
        $this->assertCount(381, $results['kjv']);
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith && joy || "free spirit"', 'whole_words' => 'yes', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        
        // Expected value of 6 is CORRECT.
        // Search faith joy (All words, whole words checked) => 5 results
        // Search free spirit (Exact Phrase) => 1 result
        $this->assertCount(6, $results['kjv']);
        $this->assertEquals(19,  $results['kjv'][0]->book);
        $this->assertEquals(51, $results['kjv'][0]->chapter);
        $this->assertEquals(12, $results['kjv'][0]->verse);
        $this->assertEquals('Restore unto me the joy of thy salvation; and uphold me with thy free spirit.', $this->_tp($results['kjv'][0]->text));
    }

    public function testBookRangeSearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith', 'reference' => 'Matt - John', 'whole_words' => TRUE]);
        $this->assertCount(29, $results['kjv']);
        $this->assertEquals(40, $results['kjv'][0]->book);
        $this->assertEquals(6,  $results['kjv'][0]->chapter);
        $this->assertEquals(30, $results['kjv'][0]->verse);
    }

    public function testProximitySearch() 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'reference' => 'Rom', 'search_type' => 'proximity']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(13, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'search_type' => 'chapter']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(151, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith hope', 'reference' => 'Rom', 'search_type' => 'book']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(43, $results['kjv']);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith PROC(5) hope', 'reference' => 'Rom', 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(10, $results['kjv']);
    }

    public function testAPIBooks() 
    {
        $Engine = new Engine();
        $Books = $Engine->actionBooks(array('language' => 'en'));
        $this->assertCount(66, $Books);
    }

    public function testSingleton() 
    {
        $Engine = Engine::getInstance();
        $this->assertInstanceOf('App\Engine', $Engine);

        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'faith']);
        $this->assertCount(338, $results['kjv']);
        $this->assertEquals(4,  $results['kjv'][0]->book);
        $this->assertEquals(12, $results['kjv'][0]->chapter);
        $this->assertEquals(7,  $results['kjv'][0]->verse);
        $this->assertEquals('My servant Moses is not so, who is faithful in all mine house.',  $this->_tp($results['kjv'][0]->text));
        $this->assertEquals(51, $results['kjv'][201]->book);
        $this->assertEquals(1,  $results['kjv'][201]->chapter);
        $this->assertEquals(4,  $results['kjv'][201]->verse);
        $this->assertEquals('Since we heard of your faith in Christ Jesus, and of the love which ye have to all the saints,',  $this->_tp($results['kjv'][201]->text));
    }

    private function _tp($text) 
    {
        return trim($text, '¶ ');
    }

    /**
     * A language keeps its 'book_list' attribute if its books_<lang> table goes away, so
     * actionBooks('ALL') can be handed a language that resolves to no model class. It has to
     * skip that language: calling into FALSE is a fatal that takes the whole request down,
     * including every language that *was* resolvable.
     */
    public function testActionBooksAllSkipsALanguageWithNoBookTable(): void
    {
        $code = 'qqz';
        $default = config('bss.defaults.language_short');

        try {
            $Language = $this->createLanguageFixture($code, 'Book Table Drift Test');
            $Language->setAttr('book_list', 1);

            $this->assertContains($code, Language::haveBookSupport(), 'Fixture is not advertised as having book support');
            $this->assertFalse(Schema::hasTable('books_' . $code), 'Fixture unexpectedly has a books table');

            $books_by_lang = (new Engine())->actionBooks(['language' => 'ALL']);

            $this->assertArrayNotHasKey($code, $books_by_lang);

            // The languages that do resolve must still come back in full.
            $this->assertArrayHasKey($default, $books_by_lang);
            $this->assertNotEmpty($books_by_lang[$default]);
        }
        finally {
            // Every write is inside the try, so an assertion failure - or a throw between the
            // two writes - cannot leave the fixture behind in the shared test database.
            $this->removeLanguageFixture($code);
        }
    }

    /**
     * getClassNameByLanguageStrict() caches a generated class for the life of the process, table
     * or no table, so a table dropped after the class loaded still resolves a truthy class name.
     * The query itself then fails, and that one language must not take the request with it.
     */
    public function testActionBooksAllSkipsALanguageWhoseTableIsDroppedAfterItsClassLoaded()
    {
        $code = 'qqw';
        $table = 'books_' . $code;
        $default = config('bss.defaults.language_short');

        try {
            $Language = $this->createLanguageFixture($code, 'Dropped Table Test');

            \Schema::create($table, function($table) {
                $table->increments('id');
                $table->string('name');
                $table->string('shortname')->nullable();
                $table->string('matching1')->nullable();
                $table->string('matching2')->nullable();
                $table->timestamps();
            });

            // Loads and caches the generated class while the table is still there.
            $this->assertNotFalse(\App\Models\Books\BookAbstract::getClassNameByLanguageStrict($code));

            $Language->setAttr('book_list', 1);

            \Schema::dropIfExists($table);

            // Still truthy - the class outlives its table, which is the whole point.
            $this->assertNotFalse(\App\Models\Books\BookAbstract::getClassNameByLanguageStrict($code));

            $books_by_lang = (new Engine())->actionBooks(['language' => 'ALL']);

            $this->assertArrayNotHasKey($code, $books_by_lang);
            $this->assertArrayHasKey($default, $books_by_lang);
            $this->assertNotEmpty($books_by_lang[$default]);
        }
        finally {
            \Schema::dropIfExists($table);
            $this->removeLanguageFixture($code);
        }
    }

    /**
     * Only a missing table is recoverable. Any other query failure - a lost or unhealthy
     * connection - has to surface, or the caller receives a short list it cannot tell apart
     * from a complete one.
     */
    public function testActionBooksAllRethrowsAFailureThatIsNotAMissingTable()
    {
        $code   = 'qqu';
        $table  = 'books_' . $code;
        $broken = sys_get_temp_dir() . '/bss_broken_connection_' . getmypid() . '.sqlite';

        // A file that is not a SQLite database: the connection opens and then every query on it
        // fails, which is what an unhealthy connection looks like from actionBooks. Deliberately
        // not a missing column - SQLite reads an unknown "column" as a string literal, so that
        // failure does not exist there.
        file_put_contents($broken, 'not a database');

        config(['database.connections.' . self::BROKEN_CONNECTION => [
            'driver'   => 'sqlite',
            'database' => $broken,
            'prefix'   => '',
        ]]);

        try {
            $Language = $this->createLanguageFixture($code, 'Broken Connection Test');

            // The table is present on the default connection, so a missing table cannot explain
            // the failure and the exception has to propagate.
            \Schema::create($table, function($table) {
                $table->increments('id');
                $table->string('name');
                $table->string('shortname')->nullable();
            });

            // App\Models\Books\Qqu is declared at the foot of this file and points at the broken
            // connection, so makeClassByLanguage() finds it already loaded and leaves it alone.
            $this->assertEquals(
                'App\Models\Books\Qqu',
                \App\Models\Books\BookAbstract::getClassNameByLanguageStrict($code)
            );

            $Language->setAttr('book_list', 1);

            $this->expectException(\Illuminate\Database\QueryException::class);

            (new Engine())->actionBooks(['language' => 'ALL']);
        }
        finally {
            \Schema::dropIfExists($table);
            $this->removeLanguageFixture($code);
            @unlink($broken);
        }
    }
}

namespace App\Models\Books;

/**
 * Stands in for a language whose book table is present but whose connection is unusable, so
 * Engine::actionBooks() has to rethrow rather than quietly drop the language from its result.
 *
 * Declared here rather than generated: makeClassByLanguage() skips a class that already exists,
 * which is what lets this one carry a deliberately broken connection.
 */
class Qqu extends BookAbstract
{
    protected $connection = \Tests\Feature\EngineTest::BROKEN_CONNECTION;
    protected $table = 'books_qqu';
}
