<?php

namespace Tests\Feature\Query;

use Tests\TestCase;
use App\Engine;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class UnicodeTest extends TestCase
{

    public function testSpanish() 
    {
        if(!Engine::isBibleEnabled('rvg')) {
            $this->markTestSkipped('Bible rvg not installed or enabled');
        }

        $Engine = Engine::freshInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'rvg', 'request' => 'Señor', 'whole_words' => FALSE]);
        $this->assertNotEmpty($results['rvg']);
        $this->assertCount(config('bss.pagination.limit'), $results['rvg']);
    }

    public function testSpanishLookup() 
    {
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

    public function testItalian() 
    {
        if(!Engine::isBibleEnabled('diodati')) {
            $this->markTestSkipped('Bible diodati not installed or enabled');
        }

        $Engine = Engine::freshInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'diodati', 'request' => 'l’uomo', 'whole_words' => FALSE]);
        $this->assertNotEmpty($results['diodati']);
        $this->assertCount(config('bss.pagination.limit'), $results['diodati']);
        $results = $Engine->actionQuery(['bible' => 'diodati', 'search' => 'l’uomo', 'whole_words' => FALSE]);
        $this->assertNotEmpty($results['diodati']);
        $this->assertCount(config('bss.pagination.limit'), $results['diodati']);

        $results = $Engine->actionQuery(['bible' => 'diodati', 'request' => '(l’uomo) (alla)', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors(), 'Failed on using implied AND');        
        $results = $Engine->actionQuery(['bible' => 'diodati', 'search' => '(l’uomo) (alla)', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors(), 'Failed on using implied AND');       

        $results = $Engine->actionQuery(['bible' => 'diodati', 'search' => '(l’uomo) PROX(5) (alla)', 'whole_words' => FALSE]);
        $this->assertTrue($Engine->hasErrors(), 'Cannot use prox terms on all_words search');
    }

    public function testHebrew() 
    {
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

    public function testArabic() 
    {
        if(!Engine::isBibleEnabled('svd')) {
            $this->markTestSkipped('Bible svd (Smith Van Dyke) not installed or enabled');
        }

        $Engine = Engine::freshInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'svd', 'request' => 'المسيح ', 'whole_words' => FALSE]);
        $this->assertNotEmpty($results['svd']);
        $this->assertCount(config('bss.pagination.limit'), $results['svd']);

        $results = $Engine->actionQuery(['bible' => 'svd', 'search' => 'المسيح ', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
        $this->assertNotEmpty($results['svd']);
        $this->assertCount(config('bss.pagination.limit'), $results['svd']);
    }

    public function testThai() 
    {
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

    public function testLatvian() 
    {
        if(!Engine::isBibleEnabled('lv_gluck_8')) {
            $this->markTestSkipped('Bible lv_gluck_8 not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        // Testing removing unsafe characters
        $search_words_arr = [];
        $search_words_arr[] = 'Iesākumā Dievs radīja debesis un zemi';
        $search_words_arr[] = 'Tad Dieva bērni redzēja cilvēku meitas ka tās bija skaistas un ņēma sev sievas kādas tiem patika';
        $search_words_arr[] = 'Un zeme bija tumša un tukša un tumsa bija pār dziļumiem un Dieva Gars lidinājās pa ūdeņu virsu';

        foreach($search_words_arr as $search_words) {
            $this->assertEquals($search_words, \App\Search::removeUnsafeCharacters($search_words));
        }

        // Search 1
        $query = [
            'bible'  => 'lv_gluck_8',
            'search' => 'Iesākumā Dievs radīja debesis un zemi.', // Genesis 1:1
            'search_type' => 'phrase',
        ];

        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['lv_gluck_8']);

        $query['search_type'] = 'all_words';
        // $query['search'] = $search_words;
        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors()); 
        $errors = $Engine->getErrors();
        $this->assertCount(1, $results['lv_gluck_8']);

        $query['search_type'] = 'any_word';
        // $query['search'] = $search_words;
        $results = $Engine->actionQuery($query);

        // Will probably results in too many results error
        // Just going to assert that it has results.
        $this->assertIsArray($results['lv_gluck_8']);
        $this->assertNotEmpty($results['lv_gluck_8']);

        // Search 1b: no softening marks
        $query = [
            'bible'  => 'lv_gluck_8',
            'search' => 'Iesakuma Dievs radīja debesis un zemi.', // Genesis 1:1
            'search_type' => 'phrase',
        ];

        // DOES NOT WORK WITH EXACT PHRASE!
        // $results = $Engine->actionQuery($query);
        // $this->assertFalse($Engine->hasErrors());
        // $this->assertCount(1, $results['lv_gluck_8']);

        $query['search_type'] = 'all_words';
        // $query['search'] = $search_words;
        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors()); 
        $errors = $Engine->getErrors();
        $this->assertCount(1, $results['lv_gluck_8']);

        $query['search_type'] = 'any_word';
        // $query['search'] = $search_words;
        $results = $Engine->actionQuery($query);

        // Will probably results in too many results error
        // Just going to assert that it has results.
        $this->assertIsArray($results['lv_gluck_8']);
        $this->assertNotEmpty($results['lv_gluck_8']);

        // Search 2
            // Customer-provided string, but does NOT exist in lv_gluck_8
            // Closest match is Genesis 1:3
        $search_2 = 'Un Dievs sacīja: „Lai top gaisma.“ Un gaisma tapa.';
        $search_2_safe = 'Un Dievs sacīja Lai top gaisma Un gaisma tapa';

        $this->assertEquals($search_2_safe, \App\Search::removeUnsafeCharacters($search_2));

        $query = [
            'bible'  => 'lv_gluck_8',
            //'bible'  => 'lvt_65,lv_gluck_8',
            'search' => $search_2,
        ];

        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());
        $errors = $Engine->getErrors();
        $this->assertNotContains('System Error. Please contact site administrator.', $errors);
        $this->assertNotContains('DATABASE ERROR:', $errors);

        $query['search_type'] = 'any_word';
        $results = $Engine->actionQuery($query);

        // Will probably results in too many results error
        // Just going to assert that it has results.
        $this->assertIsArray($results['lv_gluck_8']);
        $this->assertNotEmpty($results['lv_gluck_8']);

        $query['search_type'] = 'phrase';
        $results = $Engine->actionQuery($query);
        $this->assertTrue($Engine->hasErrors()); // No results in lv_gluck_8
        $errors = $Engine->getErrors();

        $this->assertEquals(trans('errors.no_results'), $errors[0]);
        $this->assertCount(1, $errors);  // not found only

        // Search 3: Genesis 1:16
        $search_3 = 'Un Dievs darīja divus lielus spīdekļus, lielāko spīdekli, dienu valdīt un mazāko spīdekli, naktī valdīt, — un zvaigznes.';

        $search_3_safe = 'Un Dievs darīja divus lielus spīdekļus lielāko spīdekli dienu valdīt un mazāko spīdekli naktī valdīt un zvaigznes';

        $this->assertEquals($search_3_safe, \App\Search::removeUnsafeCharacters($search_3));
        
        $query = [
            'bible'  => 'lv_gluck_8',
            'search' => $search_3,
        ];

        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());

        $query['search_type'] = 'any_word';
        $results = $Engine->actionQuery($query);

        // Will probably results in too many results error
        // Just going to assert that it has results.
        $this->assertIsArray($results['lv_gluck_8']);
        $this->assertNotEmpty($results['lv_gluck_8']);

        $query['search_type'] = 'phrase';
        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());
        $errors = $Engine->getErrors();

        // Search 4: Matt 1:23
        $search_4 = '“Redzi, jumprava būs grūta un dzemdēs Dēlu, un Viņa vārdu sauks Immanuels,” tas ir tulkots: “Dievs ar mums.”';

        $search_4_safe = 'Redzi jumprava būs grūta un dzemdēs Dēlu un Viņa vārdu sauks Immanuels tas ir tulkots Dievs ar mums';

        $this->assertEquals($search_4_safe, \App\Search::removeUnsafeCharacters($search_4));

        $query = [
            'bible'  => 'lv_gluck_8',
            'search' => $search_4,
        ];

        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());

        $query['search_type'] = 'any_word';
        $results = $Engine->actionQuery($query);

        // Will probably results in too many results error
        // Just going to assert that it has results.
        $this->assertIsArray($results['lv_gluck_8']);
        $this->assertNotEmpty($results['lv_gluck_8']);

        $query['search_type'] = 'phrase';
        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());
        $errors = $Engine->getErrors();

        // Test highlight
        $search_hl = 'saulē';
        $search_hl_safe = 'saulē';

        $this->assertEquals($search_hl_safe, \App\Search::removeUnsafeCharacters($search_hl));

        $results = $Engine->actionQuery([
            'bible'  => 'lv_gluck_8',
            'search' => $search_hl, // has softening
            'highlight' => TRUE, 
            'highlight_tag' => 'span',
            // 'whole_words' => true,
        ]);

        $this->assertFalse($Engine->hasErrors());

        foreach($results['lv_gluck_8'] as $key => $v) {
            $msg = '(k: ' . $key . ') ' . $v->book . ' ' . $v->chapter . ':' . $v->verse . ' Not highlighted: '. $search_hl;
            $this->assertStringContainsString('<span>', $v->text, $msg);
        }

        $results = $Engine->actionQuery([
            'bible'  => 'lv_gluck_8',
            'search' => 'saule', // no softening
            'highlight' => TRUE, 
            'highlight_tag' => 'span',
            // 'whole_words' => true,
        ]);

        $this->assertFalse($Engine->hasErrors());
        // print_r($results['lv_gluck_8']);

        foreach($results['lv_gluck_8'] as $key => $v) {
            $msg = '(k: ' . $key . ') ' . $v->book . ' ' . $v->chapter . ':' . $v->verse . ' Not highlighted: saule';
            $this->assertStringContainsString('<span>', $v->text, $msg);
        }

        // $st = 'Tad izgāja Sodomas ķēniņš un Gomoras ķēniņš un Adamas ķēniņš un Ceboīma ķēniņš un Belas, tas ir Coāras, ķēniņš, un taisījās pret tiem kauties Sidim ielejā,';

        // // $pattern = '/[ķk][ēe][ņn][īi][ņn][šs]/iu';
        // // // $pattern = '/[ķk][ēe][ņn][īi]/ui';
        // // // $pattern = '/[ņn][šs]/i';
        // $pattern = '/kenins/iu';

        // preg_match_all($pattern, $st, $matches);

        // print_r($matches);


        $results = $Engine->actionQuery([
            'bible'  => 'lv_gluck_8',
            'search' => 'kenins',
            'highlight' => TRUE, 
            'highlight_tag' => 'span',
            // 'whole_words' => true,
        ]);

        // print_r($Engine->getErrors());
        // $this->assertFalse($Engine->hasFatalErrors());

        foreach($results['lv_gluck_8'] as $key => $v) {
            $msg = '(k: ' . $key . ') ' . $v->book . ' ' . $v->chapter . ':' . $v->verse . ' Not highlighted: kenins';
            $this->assertStringContainsString('<span>', $v->text, $msg);
        }
    }

    public function _testLatvianWithEnglish() 
    {
        // $this->markTestIncomplete('This test takes too long to run!');
        
        if(!Engine::isBibleEnabled('lv_gluck_8')) {
            $this->markTestSkipped('Bible lv_gluck_8 not installed or enabled');
        }

        if(config('bss.parallel_search_different_languages') == 'never') {
            $this->markTestSkipped('Searching across Bbiles of different languages is disabled');  
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('passage');

        $results = $Engine->actionQuery([
            'bible'  => ['lv_gluck_8', 'kjv'],
            'search' => 'eļļ',
            'highlight' => TRUE, 
            'highlight_tag' => 'span',
            // 'whole_words' => true,
        ]);

        $this->assertFalse($Engine->hasErrors());
        
        // CONFIRMED: This bug only exists on local dev DB
        // Installed a fresh copy of db, bug didn't exist!
        // Reinstalled KJV and Gluck in old db, still broke!
        //$this->assertNotEmpty($results); // YIKES!!!

        $results = $Engine->actionQuery([
            'bible'  => ['kjv', 'lv_gluck_8'],
            'search' => 'eļļ',
            'highlight' => TRUE, 
            'highlight_tag' => 'span',
            // 'whole_words' => true,
        ]);

        $this->assertFalse($Engine->hasErrors());
        $this->assertNotEmpty($results);

        foreach($results as $p) {
            foreach($p['verse_index'] as $c => $vs) {
                foreach($vs as $v) {
                    $en = $p['verses']['kjv'][$c][$v]->text;
                    $lv = $p['verses']['lv_gluck_8'][$c][$v]->text;
                    // One or the other needs to contain the highlighted word
                    $this->assertStringContainsString('<span>', $en . $lv);
                }
            }
        }
    }

    public function testRussian() 
    {
        if(!Engine::isBibleEnabled('synodal')) {
            $this->markTestSkipped('Bible synodal not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        $query = [
            'bible'  => 'synodal',
            'search' => 'В начале сотворил Бог небо и землю.', // Genesis 1:1
            'search_type' => 'phrase',
        ];

        $search_words_arr = [];
        //$search_words_arr[] = 'В начале сотворил Бог небо и землю';
        $search_words_arr[] = 'Земля же была безвидна и пуста и тьма над бездною и Дух Божий носился над водою';
        $search_words_arr[] = 'И увидел Бог свет что он хорош и отделил Бог свет от тьмы';

        foreach($search_words_arr as $search_words) {
            $this->assertEquals($search_words, \App\Search::removeUnsafeCharacters($search_words));
        }

        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['synodal']);

        $query['search_type'] = 'all_words';
        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors()); 
        $errors = $Engine->getErrors();
        $this->assertCount(1, $results['synodal']);

        $query['search_type'] = 'any_word';
        // $query['search'] = $search_words;
        $results = $Engine->actionQuery($query);
        // Will probably results in too many results error
        // Just going to assert that it has results.
        $this->assertIsArray($results['synodal']);
        $this->assertNotEmpty($results['synodal']);

        // Search 2: Gen 1:26
        $search_2 = 'И сказал Бог: сотворим человека по образу Нашему по подобию Нашему,и да владычествуют они над рыбами морскими, и над птицами небесными, и над скотом, и над всею землею, и над всеми гадами, пресмыкающимися по земле.';

        $search_2_safe = 'И сказал Бог сотворим человека по образу Нашему по подобию Нашему и да владычествуют они над рыбами морскими и над птицами небесными и над скотом и над всею землею и над всеми гадами пресмыкающимися по земле';

        // Search 2, but with words causing difficulty removed
        $search_2_adjusted = 'И сказал Бог сотворим человека по образу Нашему по Нашему и да владычествуют они над рыбами и над небесными и над скотом и над всею землею и над всеми по земле';

        $this->assertEquals($search_2_safe, \App\Search::removeUnsafeCharacters($search_2));

        $query = [
            'bible'  => 'synodal',
            'search' => $search_2,
            'search_type' => 'phrase',
        ];

        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors());
        $this->assertCount(1, $results['synodal']);

        $query['search_type'] = 'any_word';
        $results = $Engine->actionQuery($query);
        // Will probably results in too many results error
        // Just going to assert that it has results.
        $this->assertIsArray($results['synodal']);
        $this->assertNotEmpty($results['synodal']);

        $query['search_type'] = 'all_words';
        $query['search'] = $search_2;
        $results = $Engine->actionQuery($query);
        $this->assertFalse($Engine->hasErrors()); 
        $errors = $Engine->getErrors();
        $this->assertCount(1, $results['synodal']);
    }

    public function testRussianHighlight() 
    {
        if(!Engine::isBibleEnabled('synodal')) {
            $this->markTestSkipped('Bible synodal not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        // Search: Exact text of Gen 1:26
        $search = 'И сказал Бог: сотворим человека по образу Нашему по подобию Нашему,и да владычествуют они над рыбами морскими, и над птицами небесными, и над скотом, и над всею землею, и над всеми гадами, пресмыкающимися по земле.';

        $expected_hl_phrase = '<B>И сказал Бог: сотворим человека по образу Нашему по подобию Нашему,и да владычествуют они над рыбами морскими, и над птицами небесными, и над скотом, и над всею землею, и над всеми гадами, пресмыкающимися по земле</B>.'; // .</B> ???

        $expected_hl_other = '<B>И</B> <B>сказал</B> <B>Бог</B>: <B>сотворим</B> <B>человека</B> <B>по</B> <B>образу</B> <B>Нашему</B> <B>по</B> <B>подобию</B> <B>Нашему,и</B> <B>да</B> <B>владычествуют</B> <B>они</B> <B>над</B> <B>рыбами</B> <B>морскими</B>, <B>и</B> <B>над</B> <B>птицами</B> <B>небесными</B>, <B>и</B> <B>над</B> <B>скотом</B>, <B>и</B> <B>над</B> <B>всею</B> <B>землею</B>, <B>и</B> <B>над</B> <B>всеми</B> <B>гадами</B>, <B>пресмыкающимися</B> <B>по</B> <B>земле</B>.';

        $query = [
            'bible'  => 'synodal',
            'search' => $search,
            'search_type' => 'phrase',
            'reference' => 'Genesis 1:26',
            'highlight'     => true,
            'highlight_tag' => 'B',
        ];

        $search_types = ['phrase', 'any_word', 'all_words'];
        // $search_types = ['any_word'];

        foreach($search_types as $st) {
            $stt = 'search_type = ' . $st;

            $expected = $st == 'phrase' ? $expected_hl_phrase : $expected_hl_other;

            $query['search_type'] = $st;
            $results = $Engine->actionQuery($query);
            $this->assertFalse($Engine->hasErrors(), $stt);
            $this->assertCount(1, $results['synodal'], $stt);

            //print_r($results['synodal'][0]);

            $this->assertEquals($expected, $results['synodal'][0]->text, $stt);
        }
    }

    public function testWeirdHighlightIssue() 
    {
        if(!Engine::isBibleEnabled('synodal')) {
            $this->markTestSkipped('Bible synodal not installed or enabled');
        }        

        if(!Engine::isBibleEnabled('bishops')) {
            $this->markTestSkipped('Bible bishops not installed or enabled');
        }

        if(config('bss.parallel_search_different_languages') == 'never') {
            $this->markTestSkipped('Searching across Bibles of different languages NOT enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $Engine->setDefaultPageAll(TRUE);

        // Search 2: Gen 1:26
        $search_2 = 'И сказал Бог: сотворим человека по образу Нашему по подобию Нашему,и да владычествуют они над рыбами морскими, и над птицами небесными, и над скотом, и над всею землею, и над всеми гадами, пресмыкающимися по земле.';

        $query = [
            'bible'         => ['synodal','bishops'],
            'search'        => $search_2,
            'search_type'   => 'phrase',
            'highlight'     => true,
            'highlight_tag' => 'high',
        ];

        $results = $Engine->actionQuery($query);

        $this->assertTrue($Engine->hasErrors()); // No results in Bishups
        $this->assertCount(2, $results);
        $this->assertCount(1, $results['synodal']);
        $this->assertCount(1, $results['bishops']);

        $this->assertStringContainsString('<high>', $results['synodal'][0]->text);
        $this->assertStringContainsString('</high>', $results['synodal'][0]->text);
        $this->assertStringNotContainsString('<high>', $results['bishops'][0]->text);
        $this->assertStringNotContainsString('</high>', $results['bishops'][0]->text);
    }

    public function testFrenchLookup() 
    {
        if(!Engine::isBibleEnabled('martin')) {
            $this->markTestSkipped('Bible martin not installed or enabled');
        }

        $Engine = Engine::getInstance();
        $Engine->setDefaultDataType('raw');
        $results = $Engine->actionQuery(['bible' => 'martin', 'request' => 'Ésaïe 31', 'whole_words' => FALSE]);
        $this->assertFalse($Engine->hasErrors());
    }

    public function testPassageRegexp() 
    {
        $pattern = \App\Passage::PASSAGE_REGEXP;
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