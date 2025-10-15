<?php

namespace Tests\Feature\Query;

use Tests\TestCase;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;

use App\Engine;
use App\Models\Bible;

class SearchValidationTest extends TestCase 
{

    #[DataProvider('searchTypeNoErrorDataProvider')]
    public function testSearchTypeNoError(string|null $search_type)
    {
        $Engine = Engine::getInstance();

        // No Error, Defaulting Search Type
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'hope joy', 'data_format' => 'raw', 'search_type' => $search_type]);

        if($Engine->hasErrors()) {
            $errors = $Engine->getErrors();
            $this->assertEquals(0, count($errors), 'There should be no errors, but got: ' . implode(', ', $errors));
        }

        $this->assertFalse($Engine->hasErrors());
    }

    public static function searchTypeNoErrorDataProvider()
    {
        return [
            [null], // No Error, Defaulting Search Type
            [''], // No Error, Defaulting Search Type
            ['and'], // No Error, Valid Search Type
            ['or'], // No Error, Valid Search Type
            ['xor'], // No Error, Valid Search Type
            ['all_words'], // No Error, Valid Search Type (alias)
            ['any_word'], // No Error, Valid Search Type (alias)
            ['boolean'], // No Error, Valid Search Type
        ];
    }

    #[DataProvider('badSearchTypesReturnsErrorDataProvider')]
    public function testBadSearchTypeReturnsError(string $search_type) 
    {
        $Engine = Engine::getInstance();
        
        // Has Error, invalid Search Type
        $results = $Engine->actionQuery(['bible' => 'kjv', 'search' => 'love world', 'data_format' => 'raw', 'search_type' => $search_type]);
        $this->assertTrue($Engine->hasErrors());
        $errors = $Engine->getErrors();

        $this->assertEquals(trans('errors.invalid_search.type_does_not_exist', ['type' => $search_type]), $errors[0]);
    }

    public static function badSearchTypesReturnsErrorDataProvider()
    {
        return [
            ['all_word'], // Misspelled alias, should return error
            ['any_words'],  // Misspelled alias, should return error
            ['big_cat'], // Invalid Search Type
        ];
    }

    // Todo: rebuild the test for parallel language search
    // SEPARATE CLASS
    // Use separate process, data provider, other test standards ...
    // This test was build to work around configs, but it is ugly
    public function testParallelLanguageSearch()
    {
        if(!Bible::isEnabled('tr')) {
            $this->markTestSkipped('Bible tr or Bible tyndale not installed or enabled');
        }
        
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
