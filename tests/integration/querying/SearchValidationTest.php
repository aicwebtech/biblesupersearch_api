<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use App\Engine;

class SearchValidationTest extends TestCase 
{

    public function testBadSearchType()
    {
        $Engine = Engine::getInstance();

        // No Error, Defaulting Search Type
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'love world', 'data_format' => 'raw']);
        $this->assertFalse($Engine->hasErrors());

        // No Error, Defaulting Search Type
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'love world', 'data_format' => 'raw', 'search_type' => '']);
        $this->assertFalse($Engine->hasErrors());

        // No Error, Valid Search Type
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'love world', 'data_format' => 'raw', 'search_type' => 'and']);
        $this->assertFalse($Engine->hasErrors());

        // No Error, Valid Search Type (alias)
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'love world', 'data_format' => 'raw', 'search_type' => 'all_words']);
        $this->assertFalse($Engine->hasErrors());

        // Has Error, invalid Search Type
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'love world', 'data_format' => 'raw', 'search_type' => 'big_cat']);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();

        $this->assertEquals(trans('errors.invalid_search.type_does_not_exist', ['type' => 'big_cat']), $errors[0]);
    }

    public function testParallelLanguageSearch()
    {
        $multi_bible_languages_allow = config('bss.parallel_search_different_languages');

        $this->testParallelLanguageSearchHelper('always');
        $this->testParallelLanguageSearchHelper('search_type');
        $this->testParallelLanguageSearchHelper('never');

        config(['bss.parallel_search_different_languages' => $multi_bible_languages_allow]);
    }

    protected function testParallelLanguageSearchHelper($config)
    {
        $Engine = Engine::getInstance();
        config(['bss.parallel_search_different_languages' => $config]);
        $config_txt = 'Config: ' . $config;

        // Test basic parallel Language search
        $results = $Engine->actionQuery(['bible' => ['kjv','tr'], 'search' => 'love world', 'data_format' => 'raw']);

        if($config == 'never') {
            // Check for fatal error
            $this->assertTrue($Engine->hasErrors(), $config_txt);
            $this->assertEquals(4, $Engine->getErrorLevel());
            $this->assertContains(trans('errors.invalid_search.multi_bible_languages'), $Engine->getErrors());
        } else {
            // Default search type is all words, which is always allowed
            // Will have a non-fatal error
            $this->assertTrue($Engine->hasErrors(), $config_txt);
            $this->assertLessThan(4, $Engine->getErrorLevel());
        }

        // Test parallel language search with some allowed search types
        $search_types = ['and', 'or', 'xor'];

        foreach($search_types as $st) {
            $results = $Engine->actionQuery(['bible' => ['kjv','tr'], 'search' => 'love world', 'data_format' => 'raw', 'search_type' => $st]);
            $err_text = $config_txt . ', Search Type: ' . $st;

            if($config == 'never') {
                // Check for fatal error
                $this->assertTrue($Engine->hasErrors(), $err_text);
                $this->assertEquals(4, $Engine->getErrorLevel());
                $this->assertContains(trans('errors.invalid_search.multi_bible_languages'), $Engine->getErrors());
            } else {
                // Will have a non-fatal error
                $this->assertTrue($Engine->hasErrors(), $err_text);
                $this->assertLessThan(4, $Engine->getErrorLevel());
            }
        }

        // Test parallel language search with some 'not' allowed search types
        $search_types = ['proximity', 'chapter', 'book'];

        foreach($search_types as $st) {
            $results = $Engine->actionQuery(['bible' => ['kjv','tr'], 'search' => 'love world', 'data_format' => 'raw', 'search_type' => $st]);
            $err_text = $config_txt . ', Search Type: ' . $st;

            if($config == 'never' || $config == 'search_type') {
                $err = $config == 'never' ? 'errors.invalid_search.multi_bible_languages' : 'errors.invalid_search.multi_bible_languages_type';

                // Check for fatal error
                $this->assertTrue($Engine->hasErrors(), $err_text);
                $this->assertEquals(4, $Engine->getErrorLevel());
                $this->assertContains(trans($err), $Engine->getErrors());
            } else {
                // Will have a non-fatal error
                $this->assertTrue($Engine->hasErrors(), $err_text);
                $this->assertLessThan(4, $Engine->getErrorLevel());
            }
        }

    }

}
