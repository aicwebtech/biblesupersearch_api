<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Engine;

class BooleanTest extends TestCase
{
    
    #[DataProvider('phraseNoWholewordDataProvider')]
    public function testPhraseNoWholeword(string $query, int $count, int $book, int $chapter, int $verse) 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => $query, 'search_type' => 'boolean', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($count, $results['kjv']);
        $this->assertEquals($book, $results['kjv'][0]->book);
        $this->assertEquals($chapter, $results['kjv'][0]->chapter);
        $this->assertEquals($verse, $results['kjv'][0]->verse);
    }

    public static function phraseNoWholewordDataProvider() 
    {
        return [
            [' (faith OR hope) love ', 31, 5, 7, 9],
            ['appearing "blessed hope" ', 1, 56, 2, 13],
            ['"blessed hope" appearing', 1, 56, 2, 13],
            ['me "measure of faith"', 1, 45, 12, 3],
            ['"measure of faith" me', 1, 45, 12, 3],
        ];
    }

    #[DataProvider('phraseWithWholeWordDataProvider')]
    public function testPhraseWithWholeWord(string $query, int $count, int $book, int $chapter, int $verse) 
    {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => $query, 'search_type' => 'boolean', 'whole_words' => TRUE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount($count, $results['kjv']);
        $this->assertEquals($book, $results['kjv'][0]->book);
        $this->assertEquals($chapter, $results['kjv'][0]->chapter);
        $this->assertEquals($verse, $results['kjv'][0]->verse);
    }

    public static function phraseWithWholeWordDataProvider() 
    {
        return [
            ['"blessed hope" appearing', 1, 56, 2, 13],
            ['me "measure of faith"', 1, 45, 12, 3],
        ];
    }

    #[DataProvider('booleanNotDataProvider')]
    public function testBooleanNot(string $query, int $count) 
    {
        $Engine = new Engine();
        $Engine->setDefaultDataType('raw');
 
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => $query, 'search_type' => 'boolean', 'whole_words' => FALSE, 'page_all' => TRUE]);
        $this->assertFalse($Engine->hasErrors(), 'Could not query "' . $query . '"');
        $this->assertCount($count, $results['kjv']);
    }

    public static function booleanNotDataProvider() 
    {
        return [
            ['wine -bottle', 259],
            ['wine - bottle', 259],
            ['wine NOT bottle', 259],
            ['wine AND NOT bottle', 259],
            ['NOT bottle wine', 259],
            ['NOT bottle AND wine', 259],
            ['wine NOT (bottle)', 259],
            ['NOT (bottle) AND wine', 259],
            ['wine AND NOT (bottle)', 259],
            ['wine !bottle', 259],
            ['!bottle wine', 259],
            ['wine AND !bottle', 259],
        ];
    }
 }
