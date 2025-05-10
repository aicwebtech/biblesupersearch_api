<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Depends;

use App\Engine;
use App\Models\Language;

class CommonWordTest extends TestCase 
{
    protected $EN;
    protected $LV;
    protected $config_cache;
    protected $en_cache;
    protected $lv_cache;

    public function construct()
    {
        parent::construct();
    }

    public function setUp() :void
    {
        parent::setUp();

        if(!$this->EN) {
            $this->EN = Language::findByCode('en');
        }

        if(!$this->LV) {
            $this->LV = Language::findByCode('lv');
        }

        $this->config_cache = config('bss.search_common_words');
        config(['bss.search_common_words' => 'never']);
        
        $this->en_cache = $this->EN->common_words;
        $this->lv_cache = $this->LV->common_words;
    }

    public function tearDown() :void
    {
        config(['bss.search_common_words' => $this->config_cache]);

        $this->EN->common_words = $this->en_cache;
        $this->EN->save();        

        $this->LV->common_words = $this->lv_cache;
        $this->LV->save();
    }

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
        $Engine = new Engine(); // Need new instance because this test is colliding with others

        $Language = Language::findByCode('en');
        $cache = $Language->common_words;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and', 'page_limit' => 30]);

        // No errors, because language not specified
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and', 'language' => 'bb', 'page_limit' => 30]);

        // No errors, because language not found
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30]);
        
        if($Engine->hasErrors()) {
            var_dump($Language->common_words);
            print_r($Engine->getErrors());
        }

        // No errors, because no common words
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "and"; // single word
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30]);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());        

        $Language->common_words = "a\nan\nand\nthe\nor";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30]);

        // Has errors, because and IN word list
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en', 'page_limit' => 30]);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'and']), $Engine->getErrors());


        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en', 'page_limit' => 30]);

        // Has errors, because multiple words on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());



        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'or','language' => 'en', 'page_limit' => 30]);

        // Has errors, because or on word list
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hope or faith','language' => 'en', 'page_limit' => 30]);

        // Has errors, because or on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'or']), $Engine->getErrors());


        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope or faith','language' => 'en', 'page_limit' => 30]);

        // Has errors, because multiple words on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, or']), $Engine->getErrors());

        $Language->common_words = $cache;
        $Language->save();
    }    

    // #[Depends('testQueryEn')]
    public function testQueryEnCapital() 
    {
        $Engine = new Engine(); // Need new instance because this test is colliding with others

        $Language = Language::findByCode('en');
        $cache = $Language->common_words;

        // Capitalization in Common Words
        $Language->common_words = "a\nan\nAnd\nTHE";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'and']), $Engine->getErrors());
        
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());


        // Capitalization in search keywords
        $Language->common_words = "a\nan\nand\nthe";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'THE hope And faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());        

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope ANd faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());

        // 'AND' is a boolean operator; it NEVER gets banned.
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope AND faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the']), $Engine->getErrors());

        // Capitalization in BOTH
        $Language->common_words = "a\nan\nAnd\nTHE";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'THE hope And faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());        

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope ANd faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());

        // 'AND' is a boolean operator; it NEVER gets banned.
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope AND faith','language' => 'en', 'page_limit' => 30]);
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the']), $Engine->getErrors());

        $Language->common_words = $cache;
        $Language->save();
    }

    // #[Depends('testQueryEnCapital')]
    public function testQueryEnSearchType()
    {
        $Engine = new Engine(); // Need new instance because this test is colliding with others

        $Language = Language::findByCode('en');
        $cache = $Language->common_words;

        // $search_types = [null, 'and', 'or', 'phrase', 'boolean'];
        // $search_types = [null, 'and', 'or', 'boolean'];
        $search_types = [null, 'and', 'or', 'xor', 'two_or_more', 'phrase', 'boolean', 'regexp'];

        $Language->common_words = "a\nan\nand\nthe\nor";
        $Language->save();

        foreach($search_types as $st) {
            $results = $Engine->actionQuery(['bible' => 'kjv', 'language' => 'en', 'search' => 'created the heaven', 'page_limit' => 30, 'search_type' => $st]);
            $msg = 'Search type: ' . $st;

            if($st == 'phrase' || $st == 'regexp') {
                // No errors, because phrases/regexp are allowed
                $this->assertFalse($Engine->hasErrors(), $msg);
            } else {
                $this->assertTrue($Engine->hasErrors(), $msg);
                $this->assertContains(trans('errors.common_words', ['wordlist' => 'the']), $Engine->getErrors());
            }
        }

        // This causes DB error!
        // $results = $Engine->actionQuery(['bible' => 'kjv', 'language' => 'en', 'search' => '"created the heaven"', 'page_limit' => 30, 'search_type' => 'regexp']);

        // Extra boolean search test
        $results = $Engine->actionQuery(['bible' => 'kjv', 'language' => 'en', 'search' => '"created the heaven"', 'page_limit' => 30, 'search_type' => 'boolean']);
        $this->assertFalse($Engine->hasErrors(), $msg); // Phrase within boolean - passes        

        // $results = $Engine->actionQuery(['bible' => 'kjv', 'language' => 'en', 'search' => "'created the heaven'", 'page_limit' => 30, 'search_type' => 'boolean']);
        // $this->assertFalse($Engine->hasErrors(), $msg); // Phrase within boolean - passes

        $Language->common_words = $cache;
        $Language->save();
    }

    // RE: Ran into conflichts with common words when highlighting search keywords
    // #[Depends('testQueryEnSearchType')]
    public function testQueryEnHighlight()
    {
        $Engine = new Engine(); // Need new instance because this test is colliding with others

        $Language = Language::findByCode('en');
        $cache = $Language->common_words;

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and', 'highlight' => true, 'page_limit' => 30]);

        // No errors, because language not specified
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and', 'language' => 'bb', 'highlight' => true, 'page_limit' => 30]);

        // No errors, because language not found
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'highlight' => true, 'page_limit' => 30]);

        // No errors, because no common words
        $this->assertFalse($Engine->hasErrors());

        $Language->common_words = "a\nan\nand\nthe";
        $Language->save();

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'and','language' => 'en', 'highlight' => true, 'page_limit' => 30]);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hope and faith','language' => 'en', 'highlight' => true, 'page_limit' => 30]);

        // Has errors, because and on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'and']), $Engine->getErrors());


        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'the hope and faith','language' => 'en', 'highlight' => true, 'page_limit' => 30]);

        // Has errors, because multiple words on word list
        $this->assertTrue($Engine->hasErrors());
        $this->assertContains(trans('errors.common_words', ['wordlist' => 'the, and']), $Engine->getErrors());


        $Language->common_words = $cache;
        $Language->save();
    }
    
    // #[Depends('testQueryEnHighlight')]
    public function testLanguageMismatch()
    {
        $Engine = new Engine(); // Need new instance because this test is colliding with others

        $EN = Language::findByCode('en');
        $cache_en = $EN->common_words;

        $LV = Language::findByCode('lv');
        $cache_lv = $LV->common_words;

        $EN->common_words = "";
        $EN->save();        

        $LV->common_words = "";
        $LV->save();

        $results = $Engine->actionQuery(['bible' => 'lv_gluck_8', 'search' => 'and', 'language' => 'lv', 'page_limit' => 30]);
        // No errors, because 'and' is not banned
        $this->assertFalse($Engine->hasErrors());

        $EN->common_words = "a\nan\nand\nthe\nor";
        $EN->save();

        $results = $Engine->actionQuery(['bible' => 'lv_gluck_8', 'search' => 'and', 'language' => 'lv', 'page_limit' => 30]);

        // No errors, because 'and' is not banned (only language at play is lv/Latvian)
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'lv_gluck_8,kjv', 'search' => 'and', 'language' => 'lv', 'page_limit' => 30]);

        // Errors, because 'and' is not allowed in English (via kjv)
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'lv_gluck_8', 'search' => 'and', 'language' => 'en', 'page_limit' => 30]);

        // Errors, because 'and' is not allowed in English (via UI Language)
        $this->assertTrue($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'lv_gluck_8,kjv', 'search' => 'and', 'language' => 'en', 'page_limit' => 30]);

        // Errors, because 'and' is not allowed in English (via both kjv and UI)
        $this->assertTrue($Engine->hasErrors());

        $EN->common_words = $cache_en;
        $EN->save();        

        $LV->common_words = $cache_lv;
        $LV->save();
    }
}
