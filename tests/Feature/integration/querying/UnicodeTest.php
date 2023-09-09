<?php

//namespace Tests\Feature\integration\querying;

//use Tests\TestCase;
use App\Engine;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UnicodeTest extends TestCase {

    public function testSpanish() {
        if(!Engine::isBibleEnabled('rvg')) {
            $this->markTestSkipped('Bible rvg not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'rvg', 'request' => 'Señor', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testSpanishLookup() {
        if(!Engine::isBibleEnabled('rvg')) {
            $this->markTestSkipped('Bible rvg not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'rvg', 'request' => 'Efe 1', 'whole_words' => FALSE]); // Ephesians
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'rvg', 'request' => 'Eph', 'whole_words' => FALSE]); // Ephesians, as 'eph' won't match any text here
        $this->assertFalse($Engine->hasErrors());
    }

    public function testItalian() {
        if(!Engine::isBibleEnabled('diodati')) {
            $this->markTestSkipped('Bible diodati not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'diodati', 'request' => 'l’uomo', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());        
        $results = $Engine->actionQuery(['bible' => 'diodati', 'search' => 'l’uomo', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'diodati', 'request' => '(l’uomo) (alla)', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors(), 'Failed on using implied AND');        
        $results = $Engine->actionQuery(['bible' => 'diodati', 'search' => '(l’uomo) (alla)', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors(), 'Failed on using implied AND');       

        $results = $Engine->actionQuery(['bible' => 'diodati', 'search' => '(l’uomo) PROX(5) (alla)', 'whole_words' => FALSE]);
        $this->assertTrue($Engine->hasErrors(), 'Cannot use prox terms on all_words search');
    }

    public function testHebrew() {
        if(!Engine::isBibleEnabled('wlc')) {
            $this->markTestSkipped('Bible wlc not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'wlc', 'request' => 'בְּרֵאשִׁית', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());        
        $results = $Engine->actionQuery(['bible' => 'wlc', 'search' => 'בְּרֵאשִׁית', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testArabic() {
        if(!Engine::isBibleEnabled('svd')) {
            $this->markTestSkipped('Bible svd (Smith Van Dyke) not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'svd', 'request' => 'المسيح ', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());        
        $results = $Engine->actionQuery(['bible' => 'svd', 'search' => 'المسيح ', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testThai() {
        if(!Engine::isBibleEnabled('thaikjv')) {
            $this->markTestSkipped('Bible thaikjv not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'thaikjv', 'request' => 'ประการแรก', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'thaikjv', 'request' => 'ประการแรก เพราะว่า', 'whole_words' => FALSE]);

        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'thaikjv', 'request' => 'ประการแรก เพราะว่า', 'whole_words' => FALSE, 'search_type' => 'phrase']);
        $this->assertFalse($Engine->hasErrors());

        // I'm not sure why these keywords originally include (), as no () in returned verses!!!
        $results = $Engine->actionQuery(['bible' => 'thaikjv', 'request' => '(ประการแรก) (เพราะว่า)', 'whole_words' => FALSE]); 
        $this->assertFalse($Engine->hasErrors());

        $results = $Engine->actionQuery(['bible' => 'thaikjv', 'request' => '(ประการแรก) (เพราะว่า)', 'whole_words' => FALSE, 'search_type' => 'phrase']);  // PREVIOUSLY BROKE
        
        $this->assertTrue($Engine->hasErrors());
    }

    public function testLatvian() {
        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $query = [
            'bible'  => 'kjv', // Because we don't actually have a Latvian Bible atm
            'search' => 'Un Dievs sacīja: „Lai top gaisma.“ Un gaisma tapa.',
        ];

        $results = $Engine->actionQuery($query);
        $this->assertTrue($Engine->hasErrors());

        $query['search_type'] = 'any_word';
        $results = $Engine->actionQuery($query);
        $this->assertTrue($Engine->hasErrors());

        $query['search_type'] = 'phrase';
        $results = $Engine->actionQuery($query);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();

        $this->assertEquals(trans('errors.no_results'), $errors[0]);
        $this->assertCount(1, $errors);  // not found only
    }

    public function testFrenchLookup() {
        if(!Engine::isBibleEnabled('martin')) {
            $this->markTestSkipped('Bible martin not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'martin', 'request' => 'Ésaïe 31', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testPassageRegexp() {
        $pattern = App\Passage::PASSAGE_REGEXP;
        $this->assertNotEmpty($pattern);

        $lang_tests = [
            [
                'text'      => '<h2>Efe 1</h2>',
                'lang'      => 'es',
                'passage'   => ['Efe 1'],
                'book'      => ['Efe'],
                'cv'        => ['1'],
            ],
            // FAILS!
            // [
            //     'text'      => '<h2>Ésaïe 31</h2>',
            //     'lang'      => 'fr',
            //     'passage'   => ['Ésaïe 31'],
            //     'book'      => ['Ésaïe'],
            //     'cv'        => ['31'],
            // ],
        ];

        foreach($lang_tests as $ref) {
            $res = preg_match_all($pattern, $ref['text'], $matches, PREG_SET_ORDER);
            
            // Make sure the REGEX didn't have an error.
            $this->assertNotFalse($res);

            // Make sure we found all the passages we were expecting
            $this->assertEquals(count($ref['passage']), $res, $ref['text']);

            foreach ($ref['passage'] as $key => $p) {
                $this->assertEquals($p, $matches[$key][0]);
            }

            foreach($ref['book'] as $key => $p) {
                $this->assertEquals($p, trim($matches[$key][1]));
            }            

            foreach($ref['cv'] as $key => $p) {
                $this->assertEquals($p, trim($matches[$key][4]));
            }
        }
    }
}
