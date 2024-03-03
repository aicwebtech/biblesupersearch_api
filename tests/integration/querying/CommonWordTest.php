<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;
use App\Models\Language;

class CommonWordTest extends TestCase 
{
    public function testSave() 
    {
        $Language = Language::findByCode('mh');
        $cache = $Language->common_words;

        $words = ['come', 'at', 'me', 'bro'];
        $newlines = ["\n", "\r", "\r\n"];

        foreach($newlines as $nl) {
            $Language->common_words = implode($nl, $words);
            $Language->save();
            $arr = $Language->getCommonWordsAsArray();

            $this->assertIsArray($arr);
            $this->assertNotEmpty($arr);
            $this->assertEquals($words, $Language->getCommonWordsAsArray());
        }

        $Language->common_words = $cache;
        $Language->save();
    }

    public function testQueryEn()
    {
        $Engine = Engine::getInstance();

        $Language = Language::findByCode('en');
        $cache = $Language->common_words;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and']);

        // No errors, because language not specified
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and', 'language' => 'bb']);

        // No errors, because language not found
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en']);

        // No errors, because no common words
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "a\nan\nand\nthe";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en']);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en']);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'and']), $Engine->getErrors());


        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en']);

        // Has errors, because multiple words on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());


        $Language->common_words = $cache;
        $Language->save();
    }    

    // Ran into conflichts with common words when highlighting search keywords
    public function testQueryEnHighlight()
    {
        $Engine = Engine::getInstance();

        $Language = Language::findByCode('en');
        $cache = $Language->common_words;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and', 'highlight' => true]);

        // No errors, because language not specified
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and', 'language' => 'bb', 'highlight' => true]);

        // No errors, because language not found
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'highlight' => true]);

        // No errors, because no common words
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "a\nan\nand\nthe";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'highlight' => true]);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en', 'highlight' => true]);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'and']), $Engine->getErrors());


        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en', 'highlight' => true]);

        // Has errors, because multiple words on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());


        $Language->common_words = $cache;
        $Language->save();
    }
    
}
